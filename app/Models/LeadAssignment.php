<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadAssignment extends Model
{
    protected $fillable = [
        'lead_id',
        'previous_employee_id',
        'new_employee_id',
        'assigned_by',
        'assigned_at',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function previousEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'previous_employee_id');
    }

    public function newEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'new_employee_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
