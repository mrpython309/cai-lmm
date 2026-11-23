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
        $existing = array_column(Schema::getForeignKeys('leads'), 'name');

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
