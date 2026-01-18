<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_proposals', function (Blueprint $table) {
            $table->string('ordering_mode')->default('individual')->after('fulfillment_type');
        });
    }
};
