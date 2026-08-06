<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('job_application_id')->nullable()->constrained('job_applications')->onDelete('cascade');
            $table->string('type')->default('custom'); // interview, follow_up, deadline, custom
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('remind_at');
            $table->boolean('is_completed')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
