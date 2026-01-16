<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lunch_days', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('slack_channel_id');
            $table->string('slack_message_ts')->nullable();
            $table->dateTime('deadline_at');
            $table->string('status');
            $table->timestamps();

            $table->unique(['date', 'slack_channel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lunch_days');
    }
};
