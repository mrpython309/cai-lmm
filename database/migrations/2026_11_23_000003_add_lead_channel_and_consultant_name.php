<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('lead_source_channel', 120)->nullable();
            $table->string('assigned_employee_name')->nullable();
        });

        DB::table('leads')
            ->whereIn('lead_source', DB::table('lead_options')->select('value')->where('field', 'lead_source'))
            ->update(['lead_source_channel' => DB::raw('lead_source')]);
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['lead_source_channel', 'assigned_employee_name']);
        });
    }
};
