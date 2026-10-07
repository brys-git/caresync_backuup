<?php

namespace App\Services;

/**
 * Forgot-password: issue a one-time reset link, validate it, spend it.
 */
class PasswordResetService
{
    public const TOKEN_TTL_MINUTES   = 60;
    public const MAX_REQUESTS        = 3;
    public const RATE_WINDOW_MINUTES = 15;

    /**
     * Handle a reset request. Always behaves the same from the caller's
     * point of view (no exception, no return value that reveals whether the
     * email is registered or whether the rate limit was hit).
     */
    public function request(string $email, string $ip = ''): void
    {
        $email = strtolower(trim($email));
        if ($email === '' || strlen($email) > 100) {
            return;
        }

        $db  = db_connect();
        $now = date('Y-m-d H:i:s');

        $recent = $db->table('password_resets')
            ->where('email', $email)
            ->where('created_at >=', date('Y-m-d H:i:s', time() - self::RATE_WINDOW_MINUTES * 60))
            ->countAllResults();

        $user = $db->table('users')
            ->select('user_id, first_name, email, status')
            ->where('email', $email)
            ->get()
            ->getRowArray();

        $issue = $recent < self::MAX_REQUESTS && $user && ($user['status'] ?? 'active') === 'active';

        // Every request is recorded (registered or not) so the limit counts
        // the same either way; only an allowed request for a real, active
        // account gets a token.
        $row = [
            'user_id'    => $user['user_id'] ?? null,
            'email'      => $email,
            'token_hash' => null,
            'expires_at' => null,
            'ip_address' => $ip !== '' ? substr($ip, 0, 45) : null,
            'created_at' => $now,
        ];

        if (! $issue) {
            if ($recent < self::MAX_REQUESTS + 5) { // stop the table growing under abuse
                $db->table('password_resets')->insert($row);
            }

            return;
        }

        $token = bin2hex(random_bytes(32));

        // Older outstanding links stop working as soon as a new one is issued.
        $db->table('password_resets')
            ->where('user_id', (int) $user['user_id'])
            ->where('used_at', null)
            ->where('token_hash IS NOT NULL', null, false)
            ->update(['used_at' => $now]);

        $row['token_hash'] = hash('sha256', $token);
        $row['expires_at'] = date('Y-m-d H:i:s', time() + self::TOKEN_TTL_MINUTES * 60);
        $db->table('password_resets')->insert($row);

        $this->sendLink($user, site_url('reset-password/' . $token));
    }

    /**
     * @return array{status:string, row?:array} status: valid | expired | used | invalid
     */
    public function inspect(string $token): array
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $token)) {
            return ['status' => 'invalid'];
        }

        $row = db_connect()->table('password_resets')
            ->where('token_hash', hash('sha256', $token))
            ->get()
            ->getRowArray();

        if (! $row) {
            return ['status' => 'invalid'];
        }

        if ($row['used_at'] !== null) {
            return ['status' => 'used', 'row' => $row];
        }

        if (strtotime((string) $row['expires_at']) < time()) {
            return ['status' => 'expired', 'row' => $row];
        }

        return ['status' => 'valid', 'row' => $row];
    }

    /**
     * Spend the token and set the new password in one transaction. The
     * UPDATE ... WHERE used_at IS NULL guards against a double submit.
     */
    public function complete(string $token, string $newPassword): bool
    {
        $inspection = $this->inspect($token);
        if ($inspection['status'] !== 'valid') {
            return false;
        }

        $row = $inspection['row'];
        $db  = db_connect();
        $now = date('Y-m-d H:i:s');

        $db->transBegin();

        $db->table('password_resets')
            ->where('reset_id', (int) $row['reset_id'])
            ->where('used_at', null)
            ->update(['used_at' => $now]);

        if ($db->affectedRows() !== 1) {
            $db->transRollback();

            return false;
        }

        $db->table('users')->where('user_id', (int) $row['user_id'])->update([
            'password_hash'            => password_hash($newPassword, PASSWORD_DEFAULT),
            'password_changed_at'      => $now,
            'must_change_password'     => 0,
            'temp_password_expires_at' => null,
        ]);

        // Any other outstanding links for this account are void now.
        $db->table('password_resets')
            ->where('user_id', (int) $row['user_id'])
            ->where('used_at', null)
            ->update(['used_at' => $now]);

        if ($db->transStatus() === false) {
            $db->transRollback();

            return false;
        }

        $db->transCommit();

        (new ActivityLogService())->log(
            (int) $row['user_id'],
            'updated',
            'user',
            (int) $row['user_id'],
            'Reset password using the forgot-password link'
        );

        return true;
    }

    private function sendLink(array $user, string $link): void
    {
        $name = trim((string) ($user['first_name'] ?? '')) ?: 'there';
        $body = "Hi {$name},\n\n"
            . "We received a request to reset your CareSync password. Use the link below to choose a new one:\n\n"
            . "{$link}\n\n"
            . 'This link works once and expires in ' . self::TOKEN_TTL_MINUTES . " minutes.\n"
            . "If you did not ask for this, you can ignore this email - your password will not change.\n\n- CareSync";

        (new MailService())->send(
            (string) $user['email'],
            'Reset your CareSync password',
            $body,
            'Password reset link for ' . $user['email'] . ': ' . $link
        );
    }
}
