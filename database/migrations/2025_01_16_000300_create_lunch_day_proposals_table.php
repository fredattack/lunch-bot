<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lunch_day_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lunch_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enseigne_id')->constrained()->cascadeOnDelete();
            $table->string('fulfillment_type');
            $table->string('runner_user_id')->nullable();
            $table->string('orderer_user_id')->nullable();
            $table->string('platform')->nullable();
            $table->string('status');
            $table->string('slack_message_ts')->nullable();
            $table->string('created_by_slack_user_id');
            $table->timestamps();

            $table->unique(['lunch_day_id', 'enseigne_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lunch_day_proposals');
    }
};
