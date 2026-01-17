<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_proposal_id')->constrained()->cascadeOnDelete();
            $table->string('provider_user_id');
            $table->text('description');
            $table->decimal('price_estimated', 8, 2);
            $table->decimal('price_final', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->json('audit_log')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'vendor_proposal_id', 'provider_user_id']);
            $table->index('provider_user_id');
        });
    }
};
