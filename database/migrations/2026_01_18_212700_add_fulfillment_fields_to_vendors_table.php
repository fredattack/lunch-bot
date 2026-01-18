<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->json('fulfillment_types')->default('["pickup"]')->after('cuisine_type');
            $table->boolean('allow_individual_order')->default(false)->after('fulfillment_types');
        });
    }
};
