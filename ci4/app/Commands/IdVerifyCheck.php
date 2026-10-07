<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\GovernmentIdVerification;
use Config\Services;

/**
 * Diagnoses the single most common reason Government ID Verification
 * silently does nothing: the Anthropic API key in .env not actually
 * loading into Config\GovernmentIdVerification (wrong env-key prefix,
 * still commented out, etc.) - see App\Services\
 * GovernmentIdVerificationService and the GOVERNMENT ID VERIFICATION
 * block in .env for the exact prefix rule.
 */
class IdVerifyCheck extends BaseCommand
{
    protected $group       = 'Government ID Verification';
    protected $name        = 'idverify:check';
    protected $description = 'Checks whether the Anthropic API key is loaded and makes one tiny test call to the Messages API.';
    protected $usage       = 'php spark idverify:check';
    protected $arguments   = [];
    protected $options     = [];

    public function run(array $params = [])
    {
        $config = config(GovernmentIdVerification::class);

        CLI::write('Government ID Verification configuration:', 'yellow');
        CLI::write('  Model:    ' . $config->model, 'white');
        CLI::write('  Endpoint: ' . $config->endpoint, 'white');

        if ($config->anthropicApiKey === '') {
            CLI::error('  API key:  NOT LOADED (empty). Every verification will resolve to "needs_review".');
            CLI::write('');
            CLI::write('Check ci4/.env for a line prefixed exactly "governmentidverification.anthropicApiKey"', 'yellow');
            CLI::write('(the Config class\'s short name, lowercased - not "governmentId", not "GovernmentIdVerification").', 'yellow');

            return 1;
        }

        CLI::write('  API key:  ' . $this->maskKey($config->anthropicApiKey), 'green');
        CLI::write('');
        CLI::write('Making a tiny text-only test call to the Messages API...', 'yellow');

        try {
            $response = Services::curlrequest(['timeout' => 15])->post($config->endpoint, [
                'http_errors' => false,
                'headers' => [
                    'x-api-key'         => $config->anthropicApiKey,
                    'anthropic-version' => $config->apiVersion,
                    'content-type'      => 'application/json',
                ],
                'json' => [
                    'model'      => $config->model,
                    'max_tokens' => 8,
                    'messages'   => [
                        ['role' => 'user', 'content' => 'Reply with only the word: OK'],
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            CLI::error('Request failed before a response was received: ' . $e->getMessage());

            return 1;
        }

        $status = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($status >= 200 && $status < 300) {
            CLI::write('HTTP ' . $status . ' - the API key works.', 'green');
            $decoded = json_decode($body, true);
            $text = $decoded['content'][0]['text'] ?? null;
            if (is_string($text)) {
                CLI::write('Model replied: ' . trim($text), 'white');
            }

            return 0;
        }

        CLI::error('HTTP ' . $status);
        CLI::write($body, 'red');

        return 1;
    }

    /**
     * "sk-ant-…abcd" - enough to confirm the right key is loaded without
     * printing anything usable if it leaks into a terminal log.
     */
    private function maskKey(string $key): string
    {
        if (strlen($key) <= 11) {
            return str_repeat('*', strlen($key));
        }

        return substr($key, 0, 7) . '…' . substr($key, -4);
    }
}
