<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    protected $fillable = [
        'organization_id', 'branch_id', 'assigned_employee_id',
        'lead_source', 'business_type', 'company_name', 'location', 'customer_name', 'mobile', 'email',
        'application', 'vehicle_category', 'hotspot', 'current_vehicle', 'exchange', 'recommended_model',
        'test_drive', 'finance', 'priority', 'follow_up_date', 'remarks',
    ];

    protected function casts(): array
    {
        return ['follow_up_date' => 'immutable_date'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_employee_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(LeadAssignment::class)->latest('assigned_at')->latest('id');
    }
}
