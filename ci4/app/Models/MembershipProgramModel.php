<?php

namespace App\Models;

use CodeIgniter\Model;

class MembershipProgramModel extends Model
{
    protected $table            = 'membership_programs';
    protected $primaryKey       = 'program_id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'program_name',
        'monthly_fee',
        'plan_price',
        'term_months',
        'package_id',
        'description',
        'is_active',
        'approval_status',
        'submitted_by',
        'submitted_by_branch_id',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];
}
