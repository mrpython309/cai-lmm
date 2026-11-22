<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('id')
                ->constrained('organizations')
                ->restrictOnDelete();

            $table->foreignId('branch_id')
                ->nullable()
                ->after('organization_id')
                ->constrained('branches')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['branch_id']);
            $table->dropColumn(['organization_id', 'branch_id']);
        });
    }
};
