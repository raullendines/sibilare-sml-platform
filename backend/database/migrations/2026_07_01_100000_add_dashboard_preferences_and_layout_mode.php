<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashboards', function (Blueprint $table): void {
            $table->text('layout_mode')->default('freeform')->after('grid_columns');
        });

        Schema::create('dashboard_user_preferences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->uuid('dashboard_id');
            $table->uuid('client_user_id');
            $table->json('filter_values');
            $table->timestamp('last_opened_at')->nullable();
            $table->timestamps();

            $table->unique(['dashboard_id', 'client_user_id'], 'dashboard_user_preferences_dashboard_user_unique');
            $table->index(['client_id', 'client_user_id'], 'dashboard_user_preferences_client_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_user_preferences');

        Schema::table('dashboards', function (Blueprint $table): void {
            $table->dropColumn('layout_mode');
        });
    }
};
