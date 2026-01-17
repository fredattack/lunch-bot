<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('slack');
            $table->string('provider_team_id');
            $table->string('name')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_team_id']);
        });
    }
};
