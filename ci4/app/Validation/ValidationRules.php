<?php

namespace App\Validation;

/**
 * Custom Validation Rules
 * 
 * Centralizes custom validation rules for use across the application
 * Provides domain-specific validation logic beyond CodeIgniter's built-in rules
 * 
 * Usage in controllers:
 * $this->validate([
 *     'beneficiary_name' => 'required|valid_beneficiary_name',
 *     'contact_number' => 'required|valid_philippine_phone',
 *     'plan_id' => 'required|valid_plan_available',
 * ]);
 */
class ValidationRules
{
    /**
     * Validate Philippine contact number format
     * Accepts: 09xxxxxxxxx, +639xxxxxxxxx, 09-xxxx-xxxx
     * 
     * @param string $str - Phone number to validate
     * @return bool
     */
    public static function validPhilippinePhone(?string $str): bool
    {
        if (empty($str)) {
            return false;
        }

        // Remove common formatting
        $phone = preg_replace('/[\s\-\+\(\)]/u', '', $str);

        // Check Philippine format: 09XXXXXXXXX (11 digits) or 639XXXXXXXXX
        if (strlen($phone) === 11 && substr($phone, 0, 2) === '09') {
            return ctype_digit($phone);
        }

        if (strlen($phone) === 12 && substr($phone, 0, 2) === '63') {
            return ctype_digit($phone);
        }

        return false;
    }

    /**
     * Validate beneficiary name format
     * Must contain at least 2 words (first name and last name)
     * 
     * @param string $str - Name to validate
     * @return bool
     */
    public static function validBeneficiaryName(?string $str): bool
    {
        if (empty($str) || strlen($str) < 5) {
            return false;
        }

        // Must have at least 2 words
        $parts = explode(' ', trim($str));
        return count($parts) >= 2;
    }

    /**
     * Validate relationship is appropriate for beneficiary
     * 
     * @param string $str - Relationship to validate
     * @return bool
     */
    public static function validRelationship(?string $str): bool
    {
        $validRelationships = [
            'spouse',
            'child',
            'parent',
            'sibling',
            'grandchild',
            'grandparent',
            'in-law',
            'other'
        ];

        return in_array(strtolower($str ?? ''), $validRelationships, true);
    }

    /**
     * Validate civil status value
     * 
     * @param string $str - Civil status to validate
     * @return bool
     */
    public static function validCivilStatus(?string $str): bool
    {
        $validStatuses = [
            'single',
            'married',
            'widowed',
            'divorced',
            'separated',
            'annulled'
        ];

        return in_array(strtolower($str ?? ''), $validStatuses, true);
    }

    /**
     * Validate Philippine postal code (4 digits)
     * 
     * @param string $str - Postal code to validate
     * @return bool
     */
    public static function validPhilippinePostalCode(?string $str): bool
    {
        if (empty($str)) {
            return false;
        }

        $code = trim($str);
        return strlen($code) === 4 && ctype_digit($code);
    }

    /**
     * Validate a plan is available and active
     * 
     * @param string $planId - Plan ID to check
     * @return bool
     */
    public static function validPlanAvailable(?string $planId): bool
    {
        if (empty($planId) || ! ctype_digit($planId)) {
            return false;
        }

        $db = \Config\Database::connect();
        $plan = $db->table('plans')
            ->where('plan_id', (int) $planId)
            ->where('is_available', 1)
            ->select('plan_id')
            ->get()
            ->getRowArray();

        return ! empty($plan);
    }

    /**
     * Validate a package exists and is active
     * 
     * @param string $packageId - Package ID to check
     * @return bool
     */
    public static function validPackageAvailable(?string $packageId): bool
    {
        if (empty($packageId) || ! ctype_digit($packageId)) {
            return false;
        }

        $db = \Config\Database::connect();
        $package = $db->table('packages')
            ->where('package_id', (int) $packageId)
            ->where('is_active', 1)
            ->select('package_id')
            ->get()
            ->getRowArray();

        return ! empty($package);
    }

    /**
     * Validate a service is available for the plan holder
     * 
     * @param string $serviceId - Service ID to check
     * @return bool
     */
    public static function validServiceAvailable(?string $serviceId): bool
    {
        if (empty($serviceId) || ! ctype_digit($serviceId)) {
            return false;
        }

        $db = \Config\Database::connect();
        $service = $db->table('services')
            ->where('service_id', (int) $serviceId)
            ->where('is_active', 1)
            ->select('service_id')
            ->get()
            ->getRowArray();

        return ! empty($service);
    }

    /**
     * Validate a branch exists
     * 
     * @param string $branchId - Branch ID to check
     * @return bool
     */
    public static function validBranchExists(?string $branchId): bool
    {
        if (empty($branchId) || ! ctype_digit($branchId)) {
            return false;
        }

        $db = \Config\Database::connect();
        $branch = $db->table('branches')
            ->where('branch_id', (int) $branchId)
            ->select('branch_id')
            ->get()
            ->getRowArray();

        return ! empty($branch);
    }

    /**
     * Validate amount is not greater than remaining balance
     * 
     * @param string $amount - Amount to validate
     * @param string $field - Field name (for context)
     * @param array $data - All form data (to check against other fields)
     * @return bool
     */
    public static function validAmountNotExceeding(?string $amount, ?string $field, ?array $data): bool
    {
        if (empty($amount) || ! is_numeric($amount)) {
            return false;
        }

        // This is a placeholder for more complex logic
        // In real usage, you'd check against payment history, balance, etc.
        $validAmount = (float) $amount;
        return $validAmount > 0 && $validAmount <= 999999.99;
    }

    /**
     * Validate requested date is in future (for service scheduling)
     * 
     * @param string $dateStr - Date to validate
     * @return bool
     */
    public static function validFutureDate(?string $dateStr): bool
    {
        if (empty($dateStr)) {
            return false;
        }

        try {
            $date = new \DateTime($dateStr);
            $now = new \DateTime('now');
            return $date > $now;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Validate time format (HH:MM in 24-hour format)
     * 
     * @param string $timeStr - Time to validate
     * @return bool
     */
    public static function validTimeFormat(?string $timeStr): bool
    {
        if (empty($timeStr)) {
            return false;
        }

        return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $timeStr);
    }

    /**
     * Validate end time is after start time
     * 
     * @param string $endTime - End time to validate
     * @param string $field - Field name
     * @param array $data - All form data (contains start_time)
     * @return bool
     */
    public static function validEndTimeAfterStart(?string $endTime, ?string $field, ?array $data): bool
    {
        if (empty($endTime) || empty($data['start_time'] ?? null)) {
            return false;
        }

        try {
            $start = \DateTime::createFromFormat('H:i', $data['start_time']);
            $end = \DateTime::createFromFormat('H:i', $endTime);

            return $end && $start && $end > $start;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Validate file is an allowed image type for payment proofs
     * 
     * @param \CodeIgniter\HTTP\Files\UploadedFile $file - File to validate
     * @return bool
     */
    public static function validPaymentProofImage($file): bool
    {
        if (! $file || ! $file->isValid()) {
            return false;
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        $mime = $file->getMimeType();
        $size = $file->getSize();

        return in_array($mime, $allowedTypes, true) && $size <= $maxSize;
    }

    /**
     * Validate user doesn't have duplicate pending application
     * 
     * @param string $userId - User ID
     * @param string $field - Field name
     * @param array $data - All form data
     * @return bool
     */
    public static function noDuplicatePendingApplication(?string $userId, ?string $field, ?array $data): bool
    {
        if (empty($userId) || ! ctype_digit($userId)) {
            return false;
        }

        $db = \Config\Database::connect();
        $existing = $db->table('service_requests')
            ->where('user_id', (int) $userId)
            ->where('status', 'pending')
            ->where('requested_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)', null, false)
            ->countAllResults();

        // Allow if no duplicate pending requests in last 7 days
        return $existing === 0;
    }
}
