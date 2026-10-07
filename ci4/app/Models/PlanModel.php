<?php

namespace App\Models;

use CodeIgniter\Model;

class PlanModel extends Model
{
    protected $table            = 'plans';
    protected $primaryKey       = 'plan_id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'plan_holder_id',
        'package_id',
        'program_id',
        'monthly_fee',
        'passbook_fee',
        'start_date',
        'status',
        'months_paid',
        'contribution_cycle_started_at',
        'current_cycle_number',
        'remaining_balance',
        'next_due_date',
        'payment_coverage_until',
        'membership_state',
        'overdue_months',
        'coverage_until',
        'version_id',
        'overdue_notified_at',
        'last_damayan_claim_at',
    ];
}
