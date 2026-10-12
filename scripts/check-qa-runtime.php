<?php
// Only the separately created QA MariaDB instance may be used by this check.
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->loadEnvironmentFrom('.env.qa');
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\{User,Project,Comment};
use App\Services\{NotificationService,Settings};
use App\Notifications\{SiteMail,SiteNotification};
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
$c=json_decode(file_get_contents(base_path('.local/qa-context.json')),true);
if (!app()->environment('qa') || config('database.default')!=='mysql' || config('database.connections.mysql.database')!==$c['database'] || config('database.connections.mysql.unix_socket')!==$c['socket']) throw new RuntimeException('Yalnız yeni ve ayrı QA veritabanı kullanılabilir.');
$definition=new Symfony\Component\Console\Input\InputDefinition([
    new Symfony\Component\Console\Input\InputArgument('command',Symfony\Component\Console\Input\InputArgument::REQUIRED),
    new Symfony\Component\Console\Input\InputOption('database',null,Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL),
]);
App\Services\DatabaseSafety::guard(new Illuminate\Console\Events\CommandStarting('migrate',new Symfony\Component\Console\Input\ArrayInput(['command'=>'migrate'],$definition),new Symfony\Component\Console\Output\NullOutput));
$actualDataDir=rtrim(DB::selectOne('SELECT @@datadir AS path')->path,'/');
if (realpath($actualDataDir)!==realpath($c['datadir'])) throw new RuntimeException('QA MariaDB veri dizini sahipliği doğrulanamadı.');
$checks=[];
function checkRuntime(string $name,bool $passed): void { global $checks; $checks[]=['name'=>$name,'passed'=>$passed]; if (!$passed) throw new RuntimeException('QA kontrolü başarısız: '.$name); }
checkRuntime('MariaDB migration şeması',(int)DB::table('migrations')->count()===4);
checkRuntime('Üretim dışı gerçek SMTP kapalı',config('mail.default')==='log');
$user=new User();$user->forceFill(['name'=>'Çalışma Zamanı Testi','email'=>'runtime-'.bin2hex(random_bytes(4)).'@example.test','password'=>bin2hex(random_bytes(16)).'Aa1!','email_verified_at'=>now(),'role'=>'member'])->save();
$user->notificationPreference()->create(['site_notifications'=>true,'comment_mail'=>false,'reply_mail'=>false,'project_mail'=>false]);
DB::transaction(function()use($user){
    $project=Project::create(['slug'=>'runtime-'.bin2hex(random_bytes(4)),'title'=>['tr'=>'Çalışma Zamanı Projesi'],'is_published'=>false]);
    $comment=$user->comments()->create(['body'=>'Yalnız izole MariaDB test yorumu.','project_id'=>$project->id]);
    checkRuntime('MariaDB yeni yorum pending',$comment->fresh()->status==='pending');
    $comment->forceFill(['status'=>'approved','approved_at'=>now()])->save();
    checkRuntime('Yayınlanmamış proje yorumları gizli',!Comment::publiclyVisible()->whereKey($comment->id)->exists());
    $project->is_published=true;$project->save();
    checkRuntime('Onaylı ve yayınlanmış proje yorumu görünür',Comment::publiclyVisible()->whereKey($comment->id)->exists());
    $project->is_published=false;$project->save();
});
app(NotificationService::class)->send($user,'qa_runtime','Yerel test bildirimi','Yalnız yeni QA veritabanı.',route('profile.edit'));
checkRuntime('MariaDB site bildirimi anında kayıt',$user->notifications()->count()===1);
$before=(int)DB::table('jobs')->where('queue','qa_validation')->count();
config(['queue.default'=>'database']);
DB::transaction(function()use($user,$before){
    $user->notify((new SiteMail('QA mail testi','Yalnız sahte test alıcısı kullanılıyor.',route('profile.edit')))->onQueue('qa_validation'));
    checkRuntime('Mail dış işlem commit olana kadar kuyruğa girmez',(int)DB::table('jobs')->where('queue','qa_validation')->count()===$before);
});
checkRuntime('Mail commit sonrası DB kuyruğunda',(int)DB::table('jobs')->where('queue','qa_validation')->count()===$before+1);
$failuresBefore=(int)DB::table('failed_jobs')->count();
$worker=new Process([PHP_BINARY,'artisan','--env=qa','queue:work','database','--queue=qa_validation','--once','--tries=1','--timeout=20'],base_path());
$worker->setTimeout(30);$worker->run();
checkRuntime('İzole kuyruk işi log mailer ile işlendi',$worker->isSuccessful() && (int)DB::table('jobs')->where('queue','qa_validation')->count()===$before && (int)DB::table('failed_jobs')->count()===$failuresBefore);
app()->setLocale('en');
$english=(new SiteMail('QA notification','Test body',route('profile.edit')))->toMail($user);
checkRuntime('Mail yerelleştirme EN altyapısı',$english->actionText==='View on the website');
app()->setLocale('tr');
$turkish=(new SiteMail('QA bildirimi','Test metni',route('profile.edit')))->toMail($user);
checkRuntime('Mail yerelleştirme Türkçe',$turkish->actionText==='Sitede görüntüle');
$queries=[];DB::listen(function($query)use(&$queries){$queries[]=$query->sql;});
for($n=0;$n<40;$n++)Project::create(['slug'=>'performance-'.bin2hex(random_bytes(4)),'title'=>['tr'=>'Performans örneği '.$n],'is_published'=>true,'sort_order'=>1000+$n]);
$queries=[];
$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);
$response=$kernel->handle(Illuminate\Http\Request::create('/projelerim','GET'));
$projectQueries=count($queries);
checkRuntime('Örnek veriyle sayfalama ve N+1 sınırı',$response->getStatusCode()===200 && $projectQueries<=12);
$kernel->terminate(Illuminate\Http\Request::create('/projelerim','GET'),$response);
DB::table('projects')->where('slug','like','performance-%')->update(['is_published'=>false]);
$report=['passed'=>count($checks),'checks'=>$checks,'project_page_queries'=>$projectQueries,'scope'=>'Only new QA MariaDB; no production, development data or real SMTP.'];
file_put_contents(storage_path('runtime/qa-runtime-report.json'),json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo json_encode(['passed'=>count($checks),'project_page_queries'=>$projectQueries,'report'=>'storage/runtime/qa-runtime-report.json']).PHP_EOL;
