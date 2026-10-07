<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Panel brief, section 9: SMS delivery for notifications, via Semaphore
 * (https://semaphore.co) - a Philippines-focused SMS API.
 *
 * Fill in semaphore.apiKey in your .env to enable sending. Left blank,
 * App\Services\SmsService::send() logs and no-ops instead of failing.
 */
class Semaphore extends BaseConfig
{
    /**
     * API key from your Semaphore account dashboard.
     */
    public string $apiKey = '';

    /**
     * Optional registered sender name. Semaphore uses its default
     * ("Semaphore") if this is blank.
     */
    public string $senderName = '';

    /**
     * Semaphore's REST endpoint for sending a single message.
     */
    public string $endpoint = 'https://api.semaphore.co/api/v4/messages';
}
