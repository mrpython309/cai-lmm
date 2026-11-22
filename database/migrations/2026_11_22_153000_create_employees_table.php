<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnDelete();

            $table->string('employee_id', 50)->unique();
            $table->string('name', 150);
            $table->string('email', 254)->unique();
            $table->string('mobile_number', 20);
            $table->string('designation', 100);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['organization_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
