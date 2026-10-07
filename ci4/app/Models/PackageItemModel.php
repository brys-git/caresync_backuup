<?php

namespace App\Models;

use CodeIgniter\Model;

class PackageItemModel extends Model
{
    protected $table            = 'package_items';
    protected $primaryKey       = 'item_id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'package_id',
        'item_name',
        'description',
    ];
}
