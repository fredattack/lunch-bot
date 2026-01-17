<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lunch_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('slack');
            $table->date('date');
            $table->string('provider_channel_id');
            $table->string('provider_message_ts')->nullable();
            $table->dateTime('deadline_at');
            $table->string('status');
            $table->timestamps();

            $table->unique(['organization_id', 'date', 'provider_channel_id']);
            $table->index('status');
            $table->index(['organization_id', 'status']);
        });
    }
};
