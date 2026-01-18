<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_proposals', function (Blueprint $table) {
            $table->string('deadline_time', 5)->default('11:30')->after('ordering_mode');
        });
    }
};
