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
        Schema::create('job_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('resume_id')->nullable()->constrained('resumes')->onDelete('set null');
            $table->string('company_name');
            $table->string('role');
            $table->string('salary_range')->nullable();
            $table->string('status')->default('applied'); // applied, shortlisted, interview, offer, rejected
            $table->text('job_description')->nullable();
            $table->string('job_url', 500)->nullable();
            $table->date('applied_at');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};
