<?php

namespace App\Models;

use CodeIgniter\Model;

class PlanHolderModel extends Model
{
    protected $table            = 'plan_holders';
    protected $primaryKey       = 'plan_holder_id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'user_id',
        'registered_by',
        'id_control_no',
        'coordinator',
        'application_date',
        'address_no',
        'address_street',
        'address_barangay',
        'barangay_code',
        'address_city',
        'city_municipality_code',
        'date_of_birth',
        'place_of_birth',
        'age',
        'gender',
        'civil_status',
        'citizenship',
        'height',
        'weight',
        'spouse_name',
        'spouse_birthdate',
        'spouse_occupation',
        'senior_citizen_id',
        'organization_affiliation',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_number',
        'emergency_contact_address',
        'branch_id',
        'status',
        'is_linked_account',
        'unique_identifier',
    ];
}
