<?php

namespace App\Models;

use CodeIgniter\Model;

class GovernmentIdVerificationModel extends Model
{
    protected $table            = 'government_id_verifications';
    protected $primaryKey       = 'verification_id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'user_id',
        'id_type',
        'file_path',
        'original_name',
        'mime_type',
        'verification_status',
        'match_result',
        'extracted_name',
        'extracted_birth_date',
        'extracted_gender',
        'extracted_id_number_masked',
        'mismatch_reason',
        'reviewed_by',
        'claimed_first_name',
        'claimed_middle_name',
        'claimed_last_name',
        'claimed_date_of_birth',
        'claimed_gender',
        'verification_attempts',
        'verified_at',
    ];

    /**
     * Latest verification attempt for a user, if any.
     */
    public function latestForUser(int $userId): ?array
    {
        return $this->where('user_id', $userId)
            ->orderBy('verification_id', 'DESC')
            ->first();
    }
}
