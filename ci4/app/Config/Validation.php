<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Validation\StrictRules\CreditCardRules;
use CodeIgniter\Validation\StrictRules\FileRules;
use CodeIgniter\Validation\StrictRules\FormatRules;
use CodeIgniter\Validation\StrictRules\Rules;
use App\Validation\ValidationRules as CustomValidationRules;

class Validation extends BaseConfig
{
    // --------------------------------------------------------------------
    // Setup
    // --------------------------------------------------------------------

    /**
     * Stores the classes that contain the
     * rules that are available.
     *
     * @var list<string>
     */
    public array $ruleSets = [
        Rules::class,
        FormatRules::class,
        FileRules::class,
        CreditCardRules::class,
    ];

    /**
     * Specifies the views that are used to display the
     * errors.
     *
     * @var array<string, string>
     */
    public array $templates = [
        'list'   => 'CodeIgniter\Validation\Views\list',
        'single' => 'CodeIgniter\Validation\Views\single',
    ];

    // --------------------------------------------------------------------
    // Custom Rules
    // --------------------------------------------------------------------

    /**
     * Custom validation rules for domain-specific validation
     * Maps rule name to callback function in CustomValidationRules class
     * 
     * Usage in controllers:
     * $rules = [
     *     'phone' => 'required|valid_philippine_phone',
     *     'beneficiary' => 'required|valid_beneficiary_name',
     *     'plan_id' => 'required|valid_plan_available',
     * ];
     *
     * @var array<string, string>
     */
    public array $customRules = [
        'valid_philippine_phone' => 'App\Validation\ValidationRules::validPhilippinePhone',
        'valid_beneficiary_name' => 'App\Validation\ValidationRules::validBeneficiaryName',
        'valid_relationship' => 'App\Validation\ValidationRules::validRelationship',
        'valid_civil_status' => 'App\Validation\ValidationRules::validCivilStatus',
        'valid_philippine_postal_code' => 'App\Validation\ValidationRules::validPhilippinePostalCode',
        'valid_plan_available' => 'App\Validation\ValidationRules::validPlanAvailable',
        'valid_package_available' => 'App\Validation\ValidationRules::validPackageAvailable',
        'valid_service_available' => 'App\Validation\ValidationRules::validServiceAvailable',
        'valid_branch_exists' => 'App\Validation\ValidationRules::validBranchExists',
        'valid_amount_not_exceeding' => 'App\Validation\ValidationRules::validAmountNotExceeding',
        'valid_future_date' => 'App\Validation\ValidationRules::validFutureDate',
        'valid_time_format' => 'App\Validation\ValidationRules::validTimeFormat',
        'valid_end_time_after_start' => 'App\Validation\ValidationRules::validEndTimeAfterStart',
        'valid_payment_proof_image' => 'App\Validation\ValidationRules::validPaymentProofImage',
        'no_duplicate_pending_application' => 'App\Validation\ValidationRules::noDuplicatePendingApplication',
    ];

    // --------------------------------------------------------------------
    // Rules
    // --------------------------------------------------------------------
}
