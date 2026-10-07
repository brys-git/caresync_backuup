<?php

namespace App\Services;

use Config\Services;

/**
 * Thin wrapper around CI4's email service for the plain-text account emails
 * (password reset, login details).
 *
 * Email counts as configured when a from-address is set and, for SMTP, a
 * host too. When it isn't - or sending fails - outside production the
 * message's $devLogLine (which carries the link / details) is written to the
 * log so the flow can still be tested. In production the log never receives
 * it, since log files are not a safe place for credentials.
 */
class MailService
{
    public function isConfigured(): bool
    {
        $config = config('Email');

        if (trim((string) $config->fromEmail) === '') {
            return false;
        }

        return $config->protocol !== 'smtp' || trim((string) $config->SMTPHost) !== '';
    }

    public function send(string $to, string $subject, string $body, string $devLogLine = ''): bool
    {
        $sent = false;

        if ($this->isConfigured()) {
            try {
                $email = Services::email();
                $email->setTo($to);
                $email->setSubject($subject);
                $email->setMessage($body);
                $sent = (bool) $email->send(false);

                if (! $sent) {
                    log_message('error', 'MailService: failed to send "' . $subject . '" to ' . $to . ': ' . $email->printDebugger(['headers']));
                }
            } catch (\Throwable $e) {
                log_message('error', 'MailService: ' . $e->getMessage());
            }
        } else {
            log_message('error', 'MailService: email is not configured (see the EMAIL section of .env); "' . $subject . '" to ' . $to . ' was not sent.');
        }

        if (! $sent && $devLogLine !== '' && ENVIRONMENT !== 'production') {
            log_message('error', 'DEV EMAIL FALLBACK - ' . $devLogLine);
        }

        return $sent;
    }
}
