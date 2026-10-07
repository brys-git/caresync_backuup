<?php

namespace App\Services;

use Config\Semaphore;
use Config\Services;

/**
 * Panel brief, section 9: SMS delivery for notifications, via Semaphore
 * (https://semaphore.co).
 */
class SmsService
{
    private Semaphore $config;

    public function __construct()
    {
        $this->config = config(Semaphore::class);
    }

    /**
     * Sends a single SMS. Returns false (and logs) rather than throwing
     * when no API key is configured or the request fails, so a missing
     * SMS setup never breaks the caller's own action (e.g. recording an
     * overdue notification) - matches how NotificationService::notify()
     * itself already degrades gracefully on failure.
     */
    public function send(string $toNumber, string $message): bool
    {
        $toNumber = trim($toNumber);
        if ($toNumber === '' || trim($message) === '') {
            return false;
        }

        if ($this->config->apiKey === '') {
            log_message('info', 'SmsService::send - no Semaphore API key configured, skipping SMS to ' . $toNumber);

            return false;
        }

        $payload = [
            'apikey' => $this->config->apiKey,
            'number' => $toNumber,
            'message' => $message,
        ];

        if ($this->config->senderName !== '') {
            $payload['sendername'] = $this->config->senderName;
        }

        try {
            $response = Services::curlrequest(['timeout' => 10])
                ->post($this->config->endpoint, ['form_params' => $payload]);

            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                log_message('error', 'SmsService::send - Semaphore returned HTTP ' . $status . ': ' . $response->getBody());

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            log_message('error', 'SmsService::send - ' . $e->getMessage());

            return false;
        }
    }
}
