<?php

namespace App\Models;

use CodeIgniter\Model;

class NotificationModel extends Model
{
    protected $table            = 'notifications';
    protected $primaryKey       = 'notification_id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'user_id',
        'plan_id',
        'message',
        'notification_type',
        'is_read',
        'priority',
        'read_at',
        'is_archived',
        'type',
    ];
}
