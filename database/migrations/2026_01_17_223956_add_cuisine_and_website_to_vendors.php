<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('cuisine_type')->nullable()->after('name');
            $table->string('url_website')->nullable()->after('cuisine_type');
        });
    }
};
