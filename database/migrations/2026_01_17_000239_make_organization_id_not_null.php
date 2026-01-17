<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lunch_sessions', function (Blueprint $table) {
            $table->dropUnique('lunch_days_date_slack_channel_id_unique');
        });

        Schema::table('lunch_sessions', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable(false)->change();
            $table->unique(['organization_id', 'date', 'provider_channel_id']);
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable(false)->change();
            $table->unique(['organization_id', 'name']);
        });

        Schema::table('vendor_proposals', function (Blueprint $table) {
            $table->dropUnique('lunch_day_proposals_lunch_day_id_enseigne_id_unique');
        });

        Schema::table('vendor_proposals', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable(false)->change();
            $table->unique(['organization_id', 'lunch_session_id', 'vendor_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_lunch_day_proposal_id_slack_user_id_unique');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable(false)->change();
            $table->unique(['organization_id', 'vendor_proposal_id', 'provider_user_id']);
        });
    }
};
