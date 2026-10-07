<?php

namespace App\Models;

use CodeIgniter\Model;

class BranchModel extends Model
{
    protected $table            = 'branches';
    protected $primaryKey       = 'branch_id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'branch_name',
        'address_street',
        'address_barangay',
        'address_city',
        'address_province',
        'contact_number',
        'manager_first_name',
        'manager_middle_name',
        'manager_last_name',
        'manager_extension',
        'manager_position',
        'date_established',
        'status',
    ];
}
