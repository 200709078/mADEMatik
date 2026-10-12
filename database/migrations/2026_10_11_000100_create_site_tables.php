<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_categories', function (Blueprint $t) {
            $t->id(); $t->json('name'); $t->string('slug')->unique(); $t->unsignedInteger('sort_order')->default(0); $t->timestamps();
        });
        Schema::create('projects', function (Blueprint $t) {
            $t->id(); $t->foreignId('project_category_id')->nullable()->constrained()->nullOnDelete();
            $t->json('title'); $t->string('slug')->unique(); $t->json('summary')->nullable(); $t->json('description')->nullable();
            $t->json('features')->nullable(); $t->json('technologies')->nullable(); $t->string('status',20)->default('developing')->index();
            $t->boolean('is_published')->default(false)->index(); $t->boolean('is_featured')->default(false); $t->unsignedInteger('sort_order')->default(0);
            $t->string('external_url',2048)->nullable(); $t->string('cover_path')->nullable(); $t->json('seo_title')->nullable(); $t->json('meta_description')->nullable(); $t->timestamps();
        });
        Schema::create('project_media', function (Blueprint $t) {
            $t->id(); $t->foreignId('project_id')->constrained()->cascadeOnDelete(); $t->string('path'); $t->json('alt')->nullable(); $t->unsignedInteger('sort_order')->default(0); $t->timestamps();
        });
        Schema::create('comments', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete(); $t->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete(); $t->text('body'); $t->string('status',20)->default('pending')->index();
            $t->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('moderated_at')->nullable(); $t->timestamp('approved_at')->nullable(); $t->text('moderation_note')->nullable(); $t->timestamps();
            $t->index(['project_id','parent_id','status']);
        });
        Schema::create('settings', function (Blueprint $t) { $t->id(); $t->string('key')->unique(); $t->json('value')->nullable(); $t->timestamps(); });
        Schema::create('contact_messages', function (Blueprint $t) {
            $t->id(); $t->string('name',120); $t->string('email'); $t->string('subject',180); $t->text('body'); $t->string('status',20)->default('new')->index(); $t->timestamp('read_at')->nullable(); $t->timestamps();
        });
        Schema::create('notifications', function (Blueprint $t) { $t->uuid('id')->primary(); $t->string('type'); $t->morphs('notifiable'); $t->text('data'); $t->timestamp('read_at')->nullable(); $t->timestamps(); });
        Schema::create('notification_preferences', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete(); $t->boolean('comment_mail')->default(false); $t->boolean('reply_mail')->default(false); $t->boolean('project_mail')->default(false); $t->boolean('site_notifications')->default(true); $t->timestamps();
        });
        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); $t->string('action')->index(); $t->string('subject_type')->nullable(); $t->unsignedBigInteger('subject_id')->nullable(); $t->json('metadata')->nullable(); $t->timestamps();
        });
        Schema::create('comment_reports', function (Blueprint $t) {
            $t->id(); $t->foreignId('comment_id')->constrained()->cascadeOnDelete(); $t->foreignId('user_id')->constrained()->cascadeOnDelete(); $t->string('reason',1000); $t->string('status',20)->default('open'); $t->timestamps(); $t->unique(['comment_id','user_id']);
        });
    }
    public function down(): void
    {
        foreach (['comment_reports','activity_logs','notification_preferences','notifications','contact_messages','settings','comments','project_media','projects','project_categories'] as $name) Schema::dropIfExists($name);
    }
};
