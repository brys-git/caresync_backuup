<?php

namespace App\Models;

use CodeIgniter\Model;

class PaymentModel extends Model
{
    protected $table            = 'payments';
    protected $primaryKey       = 'payment_id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'plan_id',
        'amount',
        'payment_date',
        'payment_method',
        'reference_number',
        'proof_image',
        'months_covered',
        'cycle_number',
        'official_receipt_number',
        'verified_at',
        'verified_by',
        'received_by',
        'branch_id',
        'remarks',
        'status',
        'discount_amount',
        'discount_reason',
        'coverage_start',
        'coverage_end',
    ];
}
