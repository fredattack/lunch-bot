<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->json('fulfillment_types')->nullable()->after('cuisine_type');
            $table->boolean('allow_individual_order')->default(false)->after('fulfillment_types');
        });

        DB::table('vendors')->whereNull('fulfillment_types')->update([
            'fulfillment_types' => json_encode(['pickup']),
        ]);
    }
};
