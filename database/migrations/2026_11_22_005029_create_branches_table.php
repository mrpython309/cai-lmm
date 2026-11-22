<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->string('branch_code', 50);
            $table->string('branch_name', 150);

            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('pincode', 10)->nullable();

            $table->string('contact_person', 150)->nullable();
            $table->string('contact_number', 15)->nullable();
            $table->string('email', 254)->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(
                ['organization_id', 'branch_code'],
                'branches_org_code_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
