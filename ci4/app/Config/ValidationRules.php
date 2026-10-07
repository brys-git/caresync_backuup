<?php

namespace App\Config;

/**
 * ValidationRules Configuration
 * 
 * Centralized validation rules used across controllers
 * to avoid duplication and maintain consistency
 * 
 * Usage:
 * $rules = ValidationRules::getProfileRules();
 * if (! $this->validate($rules)) { ... }
 */
class ValidationRules
{
    /**
     * User Profile Update Rules
     */
    public static function getProfileRules(): array
    {
        return [
            'first_name' => 'required|max_length[50]',
            'last_name' => 'required|max_length[50]',
            'email' => 'required|valid_email|max_length[100]',
            'contact_number' => 'permit_empty|max_length[20]',
        ];
    }

    /**
     * User Profile Update Rules with Email Uniqueness Check
     * 
     * @param int $userId - Current user ID to exclude from uniqueness check
     */
    public static function getProfileRulesWithEmailUniqueness(int $userId = 0): array
    {
        $rules = self::getProfileRules();
        $rules['email'] = 'required|valid_email|max_length[100]|is_unique[users.email,user_id,' . $userId . ']';
        return $rules;
    }

    /**
     * Plan Registration Rules
     */
    public static function getPlanRegistrationRules(): array
    {
        return [
            'date_of_birth' => 'permit_empty|valid_date',
            'contact_number' => 'required|regex_match[/^[0-9+\-()\s]+$/]|min_length[10]|max_length[20]',
            'address_barangay' => 'required|max_length[100]',
            'address_city' => 'required|max_length[100]',
            // PSGC Cloud address integration: the barangay/city name fields
            // above are only ever set by JS after a dropdown selection, but
            // these codes are the ones actually checked against PSGC Cloud
            // server-side (ClientRegistrationController::submitPlanRegistration())
            // before saving - the codes, not the names, are the source of truth.
            'city_municipality_code' => 'required|max_length[20]',
            'barangay_code' => 'required|max_length[20]',
            'civil_status' => 'required|in_list[Single,Married,Divorced,Widowed]',
            'citizenship' => 'required|max_length[50]',
            'branch_id' => 'required|numeric',
            'package_id' => 'required|numeric',
        ];
    }

    /**
     * Payment Submission Rules
     */
    public static function getPaymentRules(): array
    {
        return [
            // Panel brief, section 3: advance payment must support 1-59 months,
            // not just the fixed 1/3/6/12 set.
            'months_covered' => 'required|is_natural_no_zero|less_than_equal_to[59]',
            'amount' => 'required|decimal',
            'payment_method' => 'required|in_list[gcash,cash]',
            'reference_number' => 'required|max_length[100]',
        ];
    }

    /**
     * Service Application Rules
     */
    public static function getServiceApplicationRules(): array
    {
        return [
            'service_id' => 'required|numeric',
            'requested_date' => 'required|valid_date[Y-m-d]',
            'location_address' => 'required|min_length[10]|max_length[500]',
            'contact_person' => 'required|min_length[3]|max_length[100]',
        ];
    }

    /**
     * Staff Schedule Rules
     */
    public static function getStaffScheduleRules(): array
    {
        return [
            'user_id' => 'required|numeric',
            'branch_id' => 'required|numeric',
            'duty_date' => 'required|valid_date[Y-m-d]',
            'start_time' => 'required|valid_date[H:i]',
            'end_time' => 'required|valid_date[H:i]',
            'duty_type' => 'required|in_list[regular,emergency,training,maintenance]',
        ];
    }

    /**
     * Email Field Validation Rule
     */
    public static function getEmailRule(bool $unique = true, int $excludeUserId = 0): string
    {
        $rule = 'required|valid_email|max_length[100]';
        
        if ($unique && $excludeUserId > 0) {
            $rule .= '|is_unique[users.email,user_id,' . $excludeUserId . ']';
        } elseif ($unique) {
            $rule .= '|is_unique[users.email]';
        }
        
        return $rule;
    }

    /**
     * Get validation message overrides (optional)
     */
    public static function getValidationMessages(): array
    {
        return [
            'contact_number' => [
                'required' => 'Contact number is required',
                'regex_match' => 'Contact number contains invalid characters',
                'min_length' => 'Contact number must be at least 10 digits',
            ],
            'email' => [
                'required' => 'Email address is required',
                'valid_email' => 'Please enter a valid email address',
                'is_unique' => 'Email is already registered in the system',
            ],
            'package_id' => [
                'required' => 'Please select a package',
                'numeric' => 'Invalid package selection',
            ],
            'service_id' => [
                'required' => 'Please select a service',
                'numeric' => 'Invalid service selection',
            ],
        ];
    }
}
