<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quick_run_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quick_run_id')->constrained()->cascadeOnDelete();
            $table->string('provider_user_id');
            $table->text('description');
            $table->decimal('price_estimated', 8, 2)->nullable();
            $table->decimal('price_final', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'quick_run_id', 'provider_user_id'], 'quick_run_requests_org_run_user_unique');
            $table->index('provider_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_run_requests');
    }
};
