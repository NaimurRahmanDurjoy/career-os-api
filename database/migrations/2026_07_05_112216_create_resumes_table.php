<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('resumes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('version_name')->default('Main Version');
            $table->jsonb('parsed_content')->nullable(); // UUID & JSONB support
            $table->integer('ats_score')->default(0);
            $table->jsonb('ai_suggestions')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('status')->default('processing'); // processing, completed, failed
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resumes');
    }
};
