<?php

namespace App\Models;

use CodeIgniter\Model;

class ServiceListModel extends Model
{
    protected $table            = 'service_list';
    protected $primaryKey       = 'service_list_id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'service_name',
        'description',
        'base_price',
        'status',
        'is_available',
        'image_path',
    ];
}
