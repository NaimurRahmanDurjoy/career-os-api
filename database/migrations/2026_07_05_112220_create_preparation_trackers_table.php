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
    Schema::create('preparation_trackers', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->string('exam_type'); // BCS, Bank Job, Govt IT Exam
        $table->jsonb('syllabus_roadmap')->nullable();
        $table->integer('overall_progress')->default(0);
        $table->timestamps();
        $table->softDeletes();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('preparation_trackers');
    }
};
