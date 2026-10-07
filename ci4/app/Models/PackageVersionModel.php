<?php

namespace App\Models;

use CodeIgniter\Model;

class PackageVersionModel extends Model
{
    protected $table            = 'package_versions';
    protected $primaryKey       = 'version_id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'package_id',
        'price',
        'effective_date',
        'status',
    ];
}
