<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quick_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('provider_user_id');
            $table->string('destination');
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('deadline_at');
            $table->string('status');
            $table->text('note')->nullable();
            $table->string('provider_channel_id');
            $table->string('provider_message_ts')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['organization_id', 'status']);
            $table->index('provider_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_runs');
    }
};
