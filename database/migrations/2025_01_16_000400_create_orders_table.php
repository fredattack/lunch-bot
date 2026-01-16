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
            $table->foreignId('lunch_day_proposal_id')->constrained()->cascadeOnDelete();
            $table->string('slack_user_id');
            $table->text('description');
            $table->decimal('price_estimated', 8, 2);
            $table->decimal('price_final', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->json('audit_log')->nullable();
            $table->timestamps();

            $table->unique(['lunch_day_proposal_id', 'slack_user_id']);
            $table->index('slack_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
