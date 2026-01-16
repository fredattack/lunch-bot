<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('lunch_day_proposal_id', 'vendor_proposal_id');
        });

        Schema::table('lunch_day_proposals', function (Blueprint $table) {
            $table->renameColumn('lunch_day_id', 'lunch_session_id');
            $table->renameColumn('enseigne_id', 'vendor_id');
        });

        Schema::rename('enseignes', 'vendors');
        Schema::rename('lunch_days', 'lunch_sessions');
        Schema::rename('lunch_day_proposals', 'vendor_proposals');
    }

    public function down(): void
    {
        Schema::rename('vendor_proposals', 'lunch_day_proposals');
        Schema::rename('lunch_sessions', 'lunch_days');
        Schema::rename('vendors', 'enseignes');

        Schema::table('lunch_day_proposals', function (Blueprint $table) {
            $table->renameColumn('lunch_session_id', 'lunch_day_id');
            $table->renameColumn('vendor_id', 'enseigne_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('vendor_proposal_id', 'lunch_day_proposal_id');
        });
    }
};
