<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $fillable = [
        'lead_source', 'business_type', 'company_name', 'location', 'customer_name', 'mobile', 'email',
        'application', 'vehicle_category', 'hotspot', 'current_vehicle', 'exchange', 'recommended_model',
        'test_drive', 'finance', 'priority', 'follow_up_date', 'remarks',
        'organization_id', 'company_id', 'branch_id', 'branch_name', 'assigned_employee_id',
        'vehicle_model_id', 'variant_id', 'variant_name',
        'enquiry_type_id', 'enquiry_type', 'lead_source_id',
        'lead_status_id', 'lead_status', 'status_response_id', 'status_response',
    ];

    protected function casts(): array
    {
        return [
            'follow_up_date'       => 'immutable_date',
            'organization_id'      => 'integer',
            'company_id'           => 'integer',
            'branch_id'            => 'integer',
            'assigned_employee_id' => 'integer',
            'vehicle_model_id'     => 'integer',
            'variant_id'           => 'integer',
            'enquiry_type_id'      => 'integer',
            'lead_source_id'       => 'integer',
            'lead_status_id'       => 'integer',
            'status_response_id'   => 'integer',
        ];
    }
}
