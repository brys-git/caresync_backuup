<?php

namespace App\Models;

use CodeIgniter\Model;

class ServiceModel extends Model
{
    protected $table            = 'services';
    protected $primaryKey       = 'service_id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'plan_holder_id',
        'branch_id',
        'service_list_id',
        'package_id',
        'total_cost',
        'service_date',
        'service_time',
        'burial_location',
        'assigned_staff',
        'notes',
        'status',
    ];
}
