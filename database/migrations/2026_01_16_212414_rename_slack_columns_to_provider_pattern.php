<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lunch_days', function (Blueprint $table) {
            $table->string('provider')->default('slack')->after('id');
            $table->renameColumn('slack_channel_id', 'provider_channel_id');
            $table->renameColumn('slack_message_ts', 'provider_message_ts');
        });

        Schema::table('lunch_day_proposals', function (Blueprint $table) {
            $table->renameColumn('slack_message_ts', 'provider_message_ts');
            $table->renameColumn('created_by_slack_user_id', 'created_by_provider_user_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('slack_user_id', 'provider_user_id');
        });

        Schema::table('enseignes', function (Blueprint $table) {
            $table->renameColumn('created_by_slack_user_id', 'created_by_provider_user_id');
        });
    }
};
