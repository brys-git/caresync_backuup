<?php

namespace App\Models;

use CodeIgniter\Model;

class PackageModel extends Model
{
    protected $table            = 'packages';
    protected $primaryKey       = 'package_id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'package_name',
        'description',
        'base_price',
        'is_customizable',
        'is_available',
        'is_damayan_entitlement',
        'image_path',
        'status',
    ];
}
