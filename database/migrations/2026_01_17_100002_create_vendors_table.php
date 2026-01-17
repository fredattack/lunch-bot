<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url_menu')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->string('created_by_provider_user_id');
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });
    }
};
