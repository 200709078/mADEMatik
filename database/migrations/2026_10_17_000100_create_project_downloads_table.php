<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->json('title')->nullable();
            $table->string('platform', 40);
            $table->string('architecture', 80)->nullable();
            $table->string('distribution_type', 20)->default('direct');
            $table->string('channel', 20)->default('stable');
            $table->string('version', 80)->nullable();
            $table->string('download_url', 2048)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->date('released_at')->nullable();
            $table->json('notes')->nullable();
            $table->json('requirements')->nullable();
            $table->string('license_url', 2048)->nullable();
            $table->char('sha256', 64)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->index(['project_id', 'is_published', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_downloads');
    }
};
