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

        Schema::create('organization_installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->text('bot_token');
            $table->string('signing_secret');
            $table->string('installed_by_provider_user_id');
            $table->string('default_channel_id')->nullable();
            $table->json('scopes')->nullable();
            $table->timestamp('installed_at');
            $table->timestamps();

            $table->unique('organization_id');
        });
    }
};
