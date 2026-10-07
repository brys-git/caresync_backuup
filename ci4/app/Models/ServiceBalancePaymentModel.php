<?php

namespace App\Models;

use CodeIgniter\Model;

class ServiceBalancePaymentModel extends Model
{
    protected $table            = 'service_balance_payments';
    protected $primaryKey       = 'service_balance_payment_id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'service_balance_id',
        'paid_by_user_id',
        'amount',
        'reference_number',
        'payment_method',
        'due_date',
        'paid_at',
        'notes',
        'status',
    ];
}