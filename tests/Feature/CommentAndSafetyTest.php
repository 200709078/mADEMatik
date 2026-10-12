<?php
namespace Tests\Feature;

use App\Models\{ActivityLog, Comment, CommentReport, Project, User};
use App\Notifications\{SiteMail, SiteNotification};
use App\Services\{ModerationService, NotificationService};
use Illuminate\Support\Facades\{Artisan, DB, Mail, Notification};
use Tests\TestCase;

class CommentAndSafetyTest extends TestCase
{
    private function project(bool $published=true): Project
    {
        return Project::create(['slug'=>'project-'.bin2hex(random_bytes(4)), 'title'=>['tr'=>'Test Projesi'], 'summary'=>['tr'=>'İzole test açıklaması'], 'is_published'=>$published, 'status'=>'live']);
    }
    private function comment(User $user, string $status='pending', ?Project $project=null, ?Comment $parent=null): Comment
    {
        $comment=$user->comments()->create(['body'=>'İzole test yorumu '.bin2hex(random_bytes(4)), 'project_id'=>$project?->id, 'parent_id'=>$parent?->id]);
        if ($status!=='pending') $comment->forceFill(['status'=>$status,'approved_at'=>$status==='approved'?now():null])->save();
        return $comment->refresh();
    }
    private function admin(): User
    {
        $user=$this->createUser(true,true);
        $this->actingAs($user)->withSession(['auth.password_confirmed_at'=>time()]);
        return $user;
    }
    public function test_guests_and_unverified_members_cannot_submit_comments(): void
    {
        $this->post(route('comments.store'),['body'=>'Geçerli uzun bir yorum.'])->assertRedirect(route('login'));
        $this->actingAs($this->createUser(false))->post(route('comments.store'),['body'=>'Geçerli uzun bir yorum.'])->assertRedirect(route('verification.notice'));
        $this->assertDatabaseCount('comments',0);
    }
    public function test_every_submission_including_super_user_and_reply_starts_pending(): void
    {
        $admin=$this->createUser(true,true); $member=$this->createUser();
        $parent=$this->comment($member,'approved');
        foreach ([$member,$admin] as $user) {
            $this->actingAs($user)->post(route('comments.store'),['body'=>'Yeni yorum '.bin2hex(random_bytes(4)), 'status'=>'approved'])->assertRedirect();
            $this->actingAs($user)->post(route('comments.store'),['body'=>'Yeni yanıt '.bin2hex(random_bytes(4)), 'parent_id'=>$parent->id, 'status'=>'approved'])->assertRedirect();
        }
        $this->assertSame(4,Comment::where('status','pending')->count());
        $this->assertSame(1,Comment::where('status','approved')->count());
        $this->assertSame(4,$admin->notifications()->count());
    }
    public function test_general_and_project_comments_have_separate_scope(): void
    {
        $user=$this->createUser(); $project=$this->project();
        $general=$this->comment($user,'approved'); $specific=$this->comment($user,'approved',$project);
        $this->get(route('comments.index'))->assertOk()->assertSee($general->body)->assertDontSee($specific->body);
        $this->get(route('projects.show',$project->slug))->assertOk()->assertSee($specific->body)->assertDontSee($general->body);
    }
    public function test_unapproved_comments_and_replies_never_leak_publicly(): void
    {
        $user=$this->createUser(); $approved=$this->comment($user,'approved');
        $pending=$this->comment($user); $rejected=$this->comment($user,'rejected'); $hidden=$this->comment($user,'hidden');
        $reply=$this->comment($user,'approved',null,$approved); $pendingReply=$this->comment($user,'pending',null,$approved);
        $response=$this->get(route('comments.index'))->assertOk()->assertSee($approved->body)->assertSee($reply->body);
        foreach ([$pending,$rejected,$hidden,$pendingReply] as $comment) $response->assertDontSee($comment->body);
    }
    public function test_hidden_or_pending_parent_also_hides_an_approved_reply(): void
    {
        $user=$this->createUser(); $parent=$this->comment($user,'hidden'); $reply=$this->comment($user,'approved',null,$parent);
        $this->get(route('comments.index'))->assertOk()->assertDontSee($reply->body);
        $parent->forceFill(['status'=>'pending'])->save();
        $this->assertFalse(Comment::publiclyVisible()->whereKey($reply->id)->exists());
    }
    public function test_blocked_authors_and_unpublished_projects_hide_comments(): void
    {
        $user=$this->createUser(); $project=$this->project(false); $comment=$this->comment($user,'approved',$project);
        $this->assertFalse(Comment::publiclyVisible()->whereKey($comment->id)->exists());
        $general=$this->comment($user,'approved'); $user->forceFill(['is_blocked'=>true])->save();
        $this->get(route('comments.index'))->assertOk()->assertDontSee($general->body);
        $this->get(route('projects.show',$project->slug))->assertNotFound();
    }
    public function test_reply_cannot_cross_projects_or_use_an_unapproved_parent(): void
    {
        $member=$this->createUser(); $a=$this->project(); $b=$this->project(); $parent=$this->comment($member,'approved',$a);
        $this->actingAs($member)->post(route('comments.store'),['body'=>'Geçerli yanıt metni', 'project_id'=>$b->id, 'parent_id'=>$parent->id])->assertSessionHasErrors('body');
        $pending=$this->comment($member);
        $this->post(route('comments.store'),['body'=>'Geçerli yanıt metni', 'parent_id'=>$pending->id])->assertNotFound();
        $nested=$this->comment($member,'approved',$a,$parent);
        $this->post(route('comments.store'),['body'=>'Geçerli yanıt metni', 'project_id'=>$a->id, 'parent_id'=>$nested->id])->assertNotFound();
        $this->assertDatabaseCount('comments',3);
    }
    public function test_invalid_project_ids_and_honeypot_are_rejected(): void
    {
        $user=$this->createUser(); $this->actingAs($user);
        $this->post(route('comments.store'),['body'=>'Geçerli yorum metni', 'project_id'=>0])->assertSessionHasErrors('project_id');
        $this->post(route('comments.store'),['body'=>'Geçerli yorum metni', 'parent_id'=>0])->assertSessionHasErrors('parent_id');
        $this->post(route('comments.store'),['body'=>'Geçerli yorum metni', 'project_id'=>$this->project(false)->id])->assertNotFound();
        $this->post(route('comments.store'),['body'=>'Geçerli yorum metni', 'website'=>'bot.example.test'])->assertSessionHasErrors('website');
        $this->assertDatabaseCount('comments',0);
    }
    public function test_owner_edit_resets_approval_and_hides_existing_replies(): void
    {
        $user=$this->createUser(); $admin=$this->createUser(true,true); $comment=$this->comment($user,'approved');
        $reply=$this->comment($admin,'approved',null,$comment);
        $comment->forceFill(['moderated_by'=>$admin->id,'moderated_at'=>now(),'moderation_note'=>'Önceki karar'])->save();
        $this->actingAs($user)->patch(route('comments.update',$comment),['body'=>'Düzenlenmiş uzun yorum', 'status'=>'approved'])->assertRedirect();
        $comment->refresh(); $this->assertSame('pending',$comment->status);
        foreach (['approved_at','moderated_at','moderated_by','moderation_note'] as $field) $this->assertNull($comment->{$field});
        $this->assertFalse(Comment::publiclyVisible()->whereKey($reply->id)->exists());
        $this->assertSame(1,$admin->notifications()->count());
        $this->get(route('profile.edit'))->assertOk()->assertSee('Onay bekliyor')->assertSee($comment->body);
    }
    public function test_other_members_cannot_edit_or_delete_a_comment(): void
    {
        $owner=$this->createUser(); $other=$this->createUser(); $comment=$this->comment($owner);
        $this->actingAs($other)->get(route('comments.edit',$comment))->assertForbidden();
        $this->patch(route('comments.update',$comment),['body'=>'Yetkisiz düzenleme metni'])->assertForbidden();
        $this->delete(route('comments.destroy',$comment))->assertForbidden();
        $this->assertDatabaseHas('comments',['id'=>$comment->id,'body'=>$comment->body]);
    }
    public function test_member_cannot_access_moderation_and_admin_needs_password_confirmation(): void
    {
        $comment=$this->comment($this->createUser());
        $this->actingAs($this->createUser())->patch(route('admin.comments.update',$comment),['status'=>'approved'])->assertForbidden();
        $this->actingAs($this->createUser(true,true))->patch(route('admin.comments.update',$comment),['status'=>'approved'])->assertRedirect(route('password.confirm'));
        $this->assertSame('pending',$comment->fresh()->status);
    }
    public function test_approval_rejection_and_hiding_record_actor_timestamp_and_notify_owner(): void
    {
        $member=$this->createUser(); $comment=$this->comment($member); $admin=$this->admin();
        foreach (['approved','rejected','hidden'] as $status) {
            $this->patch(route('admin.comments.update',$comment),['status'=>$status,'moderation_note'=>'İnceleme notu'])->assertRedirect();
            $comment->refresh(); $this->assertSame($status,$comment->status); $this->assertSame($admin->id,$comment->moderated_by);
            $this->assertNotNull($comment->moderated_at); $this->assertSame('İnceleme notu',$comment->moderation_note);
            $this->assertDatabaseHas('activity_logs',['action'=>'comment.'.$status,'subject_id'=>$comment->id]);
            $status==='approved' ? $this->assertNotNull($comment->approved_at) : $this->assertNull($comment->approved_at);
        }
        $this->assertSame(3,$member->notifications()->count());
    }
    public function test_approved_reply_notifies_parent_owner_only_after_moderation(): void
    {
        $owner=$this->createUser(); $writer=$this->createUser(); $parent=$this->comment($owner,'approved');
        $reply=$this->comment($writer,'pending',null,$parent); $this->admin();
        $this->assertSame(0,$owner->notifications()->count());
        $this->patch(route('admin.comments.update',$reply),['status'=>'approved'])->assertRedirect();
        $this->assertSame(1,$owner->notifications()->where('data','like','%comment_reply%')->count());
        $this->assertSame(1,$writer->notifications()->count());
    }
    public function test_reply_and_blocked_author_approval_constraints_do_not_change_status(): void
    {
        $owner=$this->createUser(); $parent=$this->comment($owner); $reply=$this->comment($owner,'pending',null,$parent); $this->admin();
        $this->patch(route('admin.comments.update',$reply),['status'=>'approved'])->assertSessionHasErrors('status');
        $owner->forceFill(['is_blocked'=>true])->save();
        $this->patch(route('admin.comments.update',$parent),['status'=>'approved'])->assertSessionHasErrors('status');
        $this->assertSame('pending',$reply->fresh()->status); $this->assertSame('pending',$parent->fresh()->status);
    }
    public function test_duplicate_pending_limit_and_rate_limit_prevent_spam(): void
    {
        $user=$this->createUser(); $this->actingAs($user);
        $body='Aynı içerik tekrar gönderiliyor.';
        $this->post(route('comments.store'),['body'=>$body])->assertRedirect();
        $this->post(route('comments.store'),['body'=>$body])->assertSessionHasErrors('body');
        foreach (range(1,3) as $n) $this->post(route('comments.store'),['body'=>'Farklı uzun yorum '.$n])->assertRedirect();
        $this->post(route('comments.store'),['body'=>'Limit kontrolü yeni yorum'])->assertStatus(429);
        $this->travel(2)->minutes();
        $this->post(route('comments.store'),['body'=>'Beşinci uzun bekleyen yorum'])->assertRedirect();
        $this->post(route('comments.store'),['body'=>'Altıncı uzun bekleyen yorum'])->assertSessionHasErrors('body');
        $this->assertSame(5,$user->comments()->where('status','pending')->count());
    }
    public function test_comment_xss_is_rendered_as_text(): void
    {
        $comment=$this->comment($this->createUser(),'approved'); $payload='<script>window.invalidTest=true</script>';
        $comment->update(['body'=>$payload]);
        $this->get(route('comments.index'))->assertOk()->assertSee($payload)->assertDontSee($payload,false)->assertSee('&lt;script&gt;',false);
    }
    public function test_report_is_owned_deduplicated_and_not_for_hidden_content(): void
    {
        $user=$this->createUser(); $comment=$this->comment($this->createUser(),'approved'); $admin=$this->createUser(true,true);
        $this->actingAs($user)->post(route('comments.report',$comment),['reason'=>'İçeriği incelemenizi istiyorum.'])->assertRedirect();
        $this->post(route('comments.report',$comment),['reason'=>'Tekrar gönderilen aynı rapor.'])->assertRedirect();
        $this->assertDatabaseCount('comment_reports',1); $this->assertSame(1,$admin->notifications()->count());
        $comment->forceFill(['status'=>'hidden'])->save();
        $this->post(route('comments.report',$comment),['reason'=>'Gizli içeriğe rapor denemesi.'])->assertNotFound();
        $report=CommentReport::first(); $this->actingAs($admin)->withSession(['auth.password_confirmed_at'=>time()])->patch(route('admin.comment-reports.resolve',$report))->assertRedirect();
        $this->assertSame('resolved',$report->fresh()->status);
    }
    public function test_owner_deletion_cascades_replies_and_preserves_audit(): void
    {
        $owner=$this->createUser(); $comment=$this->comment($owner); $reply=$this->comment($this->createUser(),'pending',null,$comment);
        $this->actingAs($owner)->delete(route('comments.destroy',$comment))->assertRedirect();
        $this->assertDatabaseMissing('comments',['id'=>$comment->id]); $this->assertDatabaseMissing('comments',['id'=>$reply->id]);
        $this->assertDatabaseHas('activity_logs',['action'=>'comment.deleted','subject_id'=>$comment->id]);
    }
    public function test_contact_form_validates_throttles_and_notifies_admin(): void
    {
        $admin=$this->createUser(true,true); $message=['name'=>'Test Ziyaretçisi','email'=>'visitor@example.test','subject'=>'Test konusu','body'=>'Geçerli ve uzun iletişim mesajı.'];
        $this->post(route('contact.store'),$message)->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('contact_messages',['email'=>$message['email'],'status'=>'new']);
        $this->assertSame(1,$admin->notifications()->count());
        $this->post(route('contact.store'),$message+['website'=>'bot.example.test'])->assertSessionHasErrors('website');
        $this->post(route('contact.store'),['name'=>'A','email'=>'invalid','subject'=>'A','body'=>'A'])->assertSessionHasErrors(['name','email','subject','body']);
        $this->post(route('contact.store'),$message)->assertStatus(429); $this->assertDatabaseCount('contact_messages',1);
    }
    public function test_mail_preferences_and_database_preferences_apply_independently(): void
    {
        Notification::fake(); $user=$this->createUser(); $service=app(NotificationService::class);
        $user->notificationPreference()->create(['site_notifications'=>false,'comment_mail'=>false,'reply_mail'=>false,'project_mail'=>false]);
        $service->send($user,'comment_status','Durum','Mesaj',route('profile.edit'),'comment_mail');
        Notification::assertNothingSent();
        $user->notificationPreference()->update(['comment_mail'=>true]); $user->unsetRelation('notificationPreference');
        $service->send($user,'comment_status','Durum','Mesaj',route('profile.edit'),'comment_mail');
        Notification::assertSentTo($user,SiteMail::class); Notification::assertNotSentTo($user,SiteNotification::class);
        $this->assertTrue((new SiteMail('Başlık','İçerik','http://localhost/test'))->afterCommit);
    }
    public function test_queued_mail_retries_a_fake_transport_failure_and_records_failed_job(): void
    {
        config(['queue.default'=>'database']);
        $mailer=\Mockery::mock(\Illuminate\Contracts\Mail\Mailer::class);
        $mailer->shouldReceive('send')->times(3)->andThrow(new \RuntimeException('QA simulated mail transport failure'));
        $factory=\Mockery::mock(\Illuminate\Contracts\Mail\Factory::class);
        $factory->shouldReceive('mailer')->with(null)->andReturn($mailer);
        $this->app->instance(\Illuminate\Contracts\Mail\Factory::class,$factory);
        $user=$this->createUser();
        $user->notify((new SiteMail('Test bildirimi','Gerçek SMTP kullanılmıyor.',route('profile.edit')))->onQueue('qa_failure'));
        $this->assertSame(1,DB::table('jobs')->where('queue','qa_failure')->count());
        $code=Artisan::call('queue:work',['connection'=>'database','--queue'=>'qa_failure','--stop-when-empty'=>true,'--max-jobs'=>3,'--sleep'=>0,'--tries'=>3,'--timeout'=>10]);
        $this->assertSame(0,$code);
        $this->assertSame(0,DB::table('jobs')->where('queue','qa_failure')->count());
        $this->assertDatabaseCount('failed_jobs',1);
        $this->assertStringContainsString('QA simulated mail transport failure',DB::table('failed_jobs')->value('exception'));
    }
    public function test_csrf_rejects_missing_token_when_unit_test_bypass_is_disabled(): void
    {
        $this->app->detectEnvironment(fn()=>'qa');
        $data=['name'=>'Test Ziyaretçisi','email'=>'visitor@example.test','subject'=>'Test konusu','body'=>'Geçerli iletişim mesajı.'];
        $this->post(route('contact.store'),$data)->assertStatus(419);
        $token=bin2hex(random_bytes(20));
        $this->withSession(['_token'=>$token])->post(route('contact.store'),$data+['_token'=>$token])->assertRedirect();
        $this->assertDatabaseCount('contact_messages',1);
    }
    public function test_admin_comment_filters_reject_array_types_and_unknown_status(): void
    {
        $this->admin();
        $this->getJson('/admin/comments?q[]=invalid')->assertUnprocessable()->assertJsonValidationErrors('q');
        $this->getJson('/admin/comments?status[]=pending')->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->getJson('/admin/comments?status=unexpected')->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->get('/admin/comments?status=all')->assertOk();
    }
    public function test_database_option_url_and_install_cannot_bypass_ownership_guard(): void
    {
        $mysql=config('database.connections.mysql');
        config(['database.default'=>'mysql','site.db_ownership'=>'madematik_v2','database.connections.mysql'=>array_merge($mysql,['database'=>'madematik_v2','username'=>'owned_test_user','unix_socket'=>storage_path('runtime/mariadb.sock'),'url'=>null])]);
        $definition=new \Symfony\Component\Console\Input\InputDefinition([
            new \Symfony\Component\Console\Input\InputArgument('command',\Symfony\Component\Console\Input\InputArgument::REQUIRED),
            new \Symfony\Component\Console\Input\InputOption('database',null,\Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL),
        ]);
        foreach (['migrate','migrate:install','migrate:rollback','db:seed','schema:dump'] as $command) {
            $event=new \Illuminate\Console\Events\CommandStarting($command,new \Symfony\Component\Console\Input\ArrayInput(['command'=>$command,'--database'=>'sqlite'],$definition),new \Symfony\Component\Console\Output\NullOutput);
            $error=null;
            try { \App\Services\DatabaseSafety::guard($event); } catch (\RuntimeException $e) { $error=$e; }
            $this->assertNotNull($error); $this->assertStringContainsString('sahipliği',$error->getMessage());
        }
        $event=new \Illuminate\Console\Events\CommandStarting('migrate',new \Symfony\Component\Console\Input\ArrayInput(['command'=>'migrate'],$definition),new \Symfony\Component\Console\Output\NullOutput);
        foreach (['url'=>'mysql://unowned.invalid/foreign','read'=>['database'=>'foreign'],'write'=>['database'=>'foreign']] as $field=>$value) {
            config(['database.connections.mysql.'.$field=>$value]);
            $error=null;
            try { \App\Services\DatabaseSafety::guard($event); } catch (\RuntimeException $e) { $error=$e; }
            $this->assertNotNull($error); $this->assertStringContainsString('sahipliği',$error->getMessage());
            config(['database.connections.mysql.'.$field=>null]);
        }
        config(['database.default'=>'sqlite']);
        $this->assertDatabaseHas('migrations',['migration'=>'2026_10_11_000100_create_site_tables']);
    }
    public function test_https_account_pages_use_secure_cookies_and_security_headers(): void
    {
        $this->app->detectEnvironment(fn()=>'production');
        config(['session.secure'=>true,'session.http_only'=>true,'session.same_site'=>'lax']);
        $response=$this->get('https://localhost/giris')->assertOk();
        $response->assertHeader('Strict-Transport-Security','max-age=31536000')->assertHeader('X-Content-Type-Options','nosniff')->assertHeader('X-Frame-Options','SAMEORIGIN');
        $cookies=collect($response->headers->getCookies());
        $session=$cookies->first(fn($cookie)=>$cookie->getName()===config('session.cookie'));
        $this->assertNotNull($session);
        $this->assertTrue($session->isSecure()); $this->assertTrue($session->isHttpOnly());
        $this->assertSame('lax',$session->getSameSite());
    }
    public function test_destructive_db_commands_remain_blocked_and_foreign_ownership_is_rejected(): void
    {
        foreach (['db:wipe','migrate:fresh','migrate:reset','migrate:refresh'] as $command) {
            $error=null;
            try { Artisan::call($command); } catch (\RuntimeException $e) { $error=$e; }
            $this->assertNotNull($error);
            $this->assertStringContainsString('Veri koruma',$error->getMessage());
            $this->assertDatabaseHas('migrations',['migration'=>'2026_10_11_000100_create_site_tables']);
        }
        $error=null;
        try { Artisan::call('migrate'); } catch (\RuntimeException $e) { $error=$e; }
        $this->assertNotNull($error);
        $this->assertStringContainsString('sahipliği',$error->getMessage());
        $this->assertDatabaseHas('migrations',['migration'=>'2026_10_11_000100_create_site_tables']);
    }
}
