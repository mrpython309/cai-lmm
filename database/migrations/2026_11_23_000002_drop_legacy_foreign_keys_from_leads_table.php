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
        $fks = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = 'leads' 
              AND CONSTRAINT_NAME IN (
                  'leads_branch_id_foreign', 
                  'leads_assigned_employee_id_foreign', 
                  'leads_organization_id_foreign'
              )
        ");

        $existing = array_map(fn ($r) => $r->CONSTRAINT_NAME, $fks);

        Schema::table('leads', function (Blueprint $table) use ($existing) {
            if (in_array('leads_branch_id_foreign', $existing, true)) {
                $table->dropForeign('leads_branch_id_foreign');
            }
            if (in_array('leads_assigned_employee_id_foreign', $existing, true)) {
                $table->dropForeign('leads_assigned_employee_id_foreign');
            }
            if (in_array('leads_organization_id_foreign', $existing, true)) {
                $table->dropForeign('leads_organization_id_foreign');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // These legacy local master tables are not used; master data is fetched live from Mahindra CRM.
    }
};
