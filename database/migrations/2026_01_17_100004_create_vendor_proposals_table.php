<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lunch_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('fulfillment_type');
            $table->string('runner_user_id')->nullable();
            $table->string('orderer_user_id')->nullable();
            $table->string('platform')->nullable();
            $table->string('status');
            $table->string('provider_message_ts')->nullable();
            $table->string('created_by_provider_user_id');
            $table->timestamps();

            $table->unique(['organization_id', 'lunch_session_id', 'vendor_id']);
            $table->index('status');
        });
    }
};
