<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('job_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('job_id')->constrained('job_applications')->onDelete('cascade');
            $table->string('name');
            $table->string('role')->nullable();
            $table->string('email')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->date('last_contact_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('job_contacts');
    }
};
