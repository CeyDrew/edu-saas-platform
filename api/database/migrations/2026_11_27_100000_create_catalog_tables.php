<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instructor_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('summary', 300)->nullable();
            $table->text('description')->nullable();
            $table->string('category', 60)->nullable();
            $table->string('level', 20)->default('beginner');
            $table->unsignedInteger('price')->default(0)->comment('kuruş');
            $table->unsignedInteger('compare_at_price')->nullable()->comment('kuruş');
            $table->boolean('included_in_subscription')->default(true);
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status', 'published_at']);
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['course_id', 'position']);
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type', 20)->default('video');
            $table->longText('content')->nullable();
            $table->longText('transcript')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->string('video_status', 20)->default('none');
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_preview')->default(false);
            $table->timestamps();

            $table->index(['section_id', 'position']);
            $table->index(['course_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('courses');
    }
};
