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
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
            $table->string('headline')->nullable();
            $table->text('bio')->nullable();
            $table->jsonb('social_links')->default('{}');
            $table->jsonb('featured_projects')->default('[]');
            $table->boolean('is_portfolio_public')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'username',
                'headline',
                'bio',
                'social_links',
                'featured_projects',
                'is_portfolio_public'
            ]);
        });
    }
};
