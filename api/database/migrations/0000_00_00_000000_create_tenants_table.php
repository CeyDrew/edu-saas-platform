<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 63)->unique();
            $table->string('name');
            $table->string('tagline')->nullable();
            $table->json('theme')->nullable();
            $table->string('plan', 32)->default('starter');
            $table->decimal('commission_rate', 5, 2)->default(10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
