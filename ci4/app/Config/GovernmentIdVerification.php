<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Reusable Government ID Verification component - see App\Services\
 * GovernmentIdVerificationService.
 *
 * Fill in governmentId.anthropicApiKey in your .env to enable ID
 * verification. Left blank, the service logs and returns a
 * 'needs_review' result instead of failing - registration can still be
 * submitted, it just needs a manual staff check (same graceful-
 * degradation approach as App\Services\SmsService when Semaphore is
 * unconfigured).
 */
class GovernmentIdVerification extends BaseConfig
{
    /**
     * Anthropic API key from https://console.anthropic.com/.
     */
    public string $anthropicApiKey = '';

    /**
     * A current vision-capable Claude model id.
     */
    public string $model = 'claude-sonnet-5';

    public string $endpoint = 'https://api.anthropic.com/v1/messages';

    public string $apiVersion = '2023-06-01';

    /**
     * Configurable so new ID types can be added without touching any
     * form/controller code - every registration flow that uses the
     * shared verification component reads this same list.
     *
     * @var array<string, string> value => label
     */
    public array $idTypes = [
        'philid'           => 'Philippine National ID (PhilID)',
        'drivers_license'  => "Driver's License",
        'passport'         => 'Passport',
        'umid'             => 'UMID',
        'sss_id'           => 'SSS ID',
        'prc_id'           => 'PRC ID',
        'postal_id'        => 'Postal ID',
        'voters_id'        => "Voter's ID",
        'other'            => 'Other Government-Issued ID',
    ];

    /**
     * Max upload size in bytes for an ID image (8 MB).
     */
    public int $maxFileSizeBytes = 8 * 1024 * 1024;

    /**
     * MIME types accepted for upload or camera capture. HEIC/HEIF is
     * intentionally excluded - Claude's vision API does not accept it,
     * and browsers/phones that produce it can nearly always be set to
     * capture JPEG instead.
     *
     * @var array<int, string>
     */
    public array $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
}
