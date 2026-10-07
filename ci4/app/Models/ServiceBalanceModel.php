<?php

namespace App\Models;

use CodeIgniter\Model;

class ServiceBalanceModel extends Model
{
    protected $table            = 'service_balances';
    protected $primaryKey       = 'service_balance_id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'application_id',
        'service_id',
        'plan_holder_id',
        'branch_id',
        'service_type',
        'service_name',
        'package_name',
        'package_cost',
        'monthly_fee',
        'months_paid',
        'total_contributions',
        'assistance_amount',
        'remaining_balance',
        'installment_amount',
        'due_date',
        'next_due_date',
        'beneficiary_user_id',
        'beneficiary_name',
        'beneficiary_relationship',
        'acknowledgement_notes',
        'beneficiary_acknowledged_at',
        'acknowledged_by',
        'status',
    ];
}