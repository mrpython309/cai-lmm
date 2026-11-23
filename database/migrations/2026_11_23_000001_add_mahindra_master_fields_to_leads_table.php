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
        Schema::table('leads', function (Blueprint $table) {
            // Organization & Company
            if (!Schema::hasColumn('leads', 'organization_id')) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('leads', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->after('company_name');
            }

            // Branch / Dealer Location
            if (!Schema::hasColumn('leads', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('location');
            }
            if (!Schema::hasColumn('leads', 'branch_name')) {
                $table->string('branch_name', 255)->nullable()->after('location');
            }
            if (!Schema::hasColumn('leads', 'assigned_employee_id')) {
                $table->unsignedBigInteger('assigned_employee_id')->nullable()->after('location');
            }

            // Vehicle Model & Variant
            if (!Schema::hasColumn('leads', 'vehicle_model_id')) {
                $table->unsignedBigInteger('vehicle_model_id')->nullable()->after('recommended_model');
            }
            if (!Schema::hasColumn('leads', 'variant_id')) {
                $table->unsignedBigInteger('variant_id')->nullable()->after('recommended_model');
            }
            if (!Schema::hasColumn('leads', 'variant_name')) {
                $table->string('variant_name', 120)->nullable()->after('recommended_model');
            }

            // Enquiry Type & Source
            if (!Schema::hasColumn('leads', 'enquiry_type_id')) {
                $table->unsignedBigInteger('enquiry_type_id')->nullable()->after('lead_source');
            }
            if (!Schema::hasColumn('leads', 'enquiry_type')) {
                $table->string('enquiry_type', 120)->nullable()->after('lead_source');
            }
            if (!Schema::hasColumn('leads', 'lead_source_id')) {
                $table->unsignedBigInteger('lead_source_id')->nullable()->after('lead_source');
            }

            // Lead Status & Response
            if (!Schema::hasColumn('leads', 'lead_status_id')) {
                $table->unsignedBigInteger('lead_status_id')->nullable()->after('priority');
            }
            if (!Schema::hasColumn('leads', 'lead_status')) {
                $table->string('lead_status', 120)->nullable()->after('priority');
            }
            if (!Schema::hasColumn('leads', 'status_response_id')) {
                $table->unsignedBigInteger('status_response_id')->nullable()->after('priority');
            }
            if (!Schema::hasColumn('leads', 'status_response')) {
                $table->string('status_response', 120)->nullable()->after('priority');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $columnsToDrop = [
                'organization_id',
                'assigned_employee_id',
                'branch_name',
                'vehicle_model_id',
                'variant_id',
                'variant_name',
                'enquiry_type_id',
                'enquiry_type',
                'lead_source_id',
                'lead_status_id',
                'lead_status',
                'status_response_id',
                'status_response',
            ];

            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('leads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
