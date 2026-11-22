<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LeadAssignmentController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadOptionController;
use App\Http\Controllers\OrganizationController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:10,1');
});

Route::middleware(['auth', 'cache.headers:private;no_store'])->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/', [DashboardController::class, 'create'])->name('leads.create');
    Route::get('/leads', [DashboardController::class, 'index'])->name('leads.index');
    Route::get('/leads/{lead}/edit', [DashboardController::class, 'edit'])->name('leads.edit');
    Route::get('/organizations', [DashboardController::class, 'organizations'])->name('organizations.index');
    Route::get('/branches', [DashboardController::class, 'branches'])->name('branches.index');
    Route::get('/employees', [DashboardController::class, 'employees'])->name('employees.index');

    // These same-origin JSON routes use the web session and CSRF middleware.
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/lead-options', LeadOptionController::class)->name('lead-options');
        Route::apiResource('leads', LeadController::class)->except('update');
        Route::put('/leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
        Route::get('/leads/{lead}/assignments', [LeadAssignmentController::class, 'index'])->name('leads.assignments.index');
        Route::post('/leads/{lead}/assign', [LeadAssignmentController::class, 'assign'])->name('leads.assign');

        Route::apiResource('organizations', OrganizationController::class)->except('update');
        Route::put('/organizations/{organization}', [OrganizationController::class, 'update'])->name('organizations.update');

        Route::apiResource('branches', BranchController::class)->except('update');
        Route::put('/branches/{branch}', [BranchController::class, 'update'])->name('branches.update');

        Route::apiResource('employees', EmployeeController::class)->except('update');
        Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    });
});
