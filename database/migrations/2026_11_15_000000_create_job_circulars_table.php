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
        Schema::create('job_circulars', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('organization');
            $table->string('category')->default('govt'); // 'govt', 'private', 'remote', 'bank'
            $table->string('location')->default('Bangladesh');
            $table->string('job_type')->default('full-time'); // 'full-time', 'part-time', 'contract', 'remote'
            $table->string('experience_level')->nullable(); // 'Fresher', '1-3 years', 'Grade 9-10'
            $table->string('salary_range')->nullable(); // '৳২২,০০০ - ৳৫৩,০৬০'
            $table->timestamp('application_deadline')->nullable()->index();
            $table->text('source_url')->nullable(); // Teletalk or Official apply link
            $table->text('circular_pdf_url')->nullable(); // PDF download url
            $table->text('description');
            $table->text('requirements')->nullable();
            $table->jsonb('skills_tags')->default('[]');
            $table->string('source_type')->default('admin'); // 'admin', 'rss_scraper', 'api_remote'
            $table->string('external_id')->nullable()->index();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_circulars');
    }
};
