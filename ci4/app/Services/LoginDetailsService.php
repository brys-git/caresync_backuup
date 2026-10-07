<?php

namespace App\Services;

/**
 * Temporary passwords for accounts created (or reset) by staff-side users.
 *
 * The password is generated here, stored only as a hash, flagged
 * must_change_password = 1, and given an expiry. The plain text exists just
 * long enough to be emailed and shown once on the confirmation screen.
 */
class LoginDetailsService
{
    public const TEMP_PASSWORD_DAYS = 7;

    // No 0/O, 1/l/I - these get read out over the phone and typed from SMS.
    private const UPPER   = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    private const LOWER   = 'abcdefghijkmnopqrstuvwxyz';
    private const DIGITS  = '23456789';
    private const SYMBOLS = '@#$%&*?';

    public function generatePassword(int $length = 12): string
    {
        $chars = [
            $this->pick(self::UPPER),
            $this->pick(self::LOWER),
            $this->pick(self::DIGITS),
            $this->pick(self::SYMBOLS),
        ];

        $all = self::UPPER . self::LOWER . self::DIGITS . self::SYMBOLS;
        while (count($chars) < $length) {
            $chars[] = $this->pick($all);
        }

        // Fisher-Yates with a CSPRNG so the guaranteed characters are not
        // always in the first four positions.
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }

    public function expiryTimestamp(): string
    {
        return date('Y-m-d H:i:s', strtotime('+' . self::TEMP_PASSWORD_DAYS . ' days'));
    }

    /**
     * Put a brand new temporary password on an existing account.
     *
     * @return array<string,mixed>|null the login details, or null when the user does not exist
     */
    public function reissue(int $userId): ?array
    {
        $db   = db_connect();
        $user = $db->table('users u')
            ->select('u.user_id, u.username, u.email, u.first_name, u.last_name, u.role_id, r.role_name, b.branch_name')
            ->join('roles r', 'r.role_id = u.role_id', 'left')
            ->join('branches b', 'b.branch_id = u.branch_id', 'left')
            ->where('u.user_id', $userId)
            ->get()
            ->getRowArray();

        if (! $user) {
            return null;
        }

        $password = $this->generatePassword();
        $expires  = $this->expiryTimestamp();

        $db->table('users')->where('user_id', $userId)->update([
            'password_hash'            => password_hash($password, PASSWORD_DEFAULT),
            'must_change_password'     => 1,
            'temp_password_expires_at' => $expires,
        ]);

        return $this->details($user, $password, $expires);
    }

    /**
     * @param array<string,mixed> $user needs username, email, first_name, last_name
     * @return array<string,mixed>
     */
    public function details(array $user, string $password, string $expiresAt): array
    {
        return [
            'user_id'    => (int) ($user['user_id'] ?? 0),
            'name'       => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
            'first_name' => (string) ($user['first_name'] ?? ''),
            'username'   => (string) ($user['username'] ?? ''),
            'email'      => (string) ($user['email'] ?? ''),
            'role_name'  => (string) ($user['role_name'] ?? ''),
            'branch'     => (string) ($user['branch_name'] ?? ''),
            'password'   => $password,
            'expires_at' => $expiresAt,
            'expires_label' => date('F j, Y g:i A', strtotime($expiresAt)),
            'sign_in_url'   => site_url('login'),
            'emailed'    => null,
        ];
    }

    /**
     * Plain-text block staff can copy and send by SMS / Messenger.
     */
    public function shareText(array $d): string
    {
        return "CareSync login details\n"
            . "Sign in: {$d['sign_in_url']}\n"
            . "Username: {$d['username']}\n"
            . ($d['email'] !== '' ? "Email: {$d['email']}\n" : '')
            . "Temporary password: {$d['password']}\n"
            . "Valid until: {$d['expires_label']}\n"
            . 'You will be asked to set a new password the first time you sign in.';
    }

    /**
     * Email the login details. Returns whether the email actually went out;
     * outside production, when email is not configured, the details are
     * written to the log instead so the flow can be tested.
     */
    public function email(array $d): bool
    {
        if ($d['email'] === '') {
            return false;
        }

        $name = $d['first_name'] !== '' ? $d['first_name'] : 'there';
        $body = "Hello {$name},\n\n"
            . "An account has been set up for you on CareSync, the member records system of Kaagapay.\n\n"
            . "Sign in here: {$d['sign_in_url']}\n"
            . "Username: {$d['username']}  (you can also sign in with your email: {$d['email']})\n"
            . "Temporary password: {$d['password']}\n"
            . "This temporary password works until {$d['expires_label']}.\n\n"
            . "You must change this password the first time you sign in. If it expires, ask your branch to send your login details again.\n\n"
            . '- CareSync';

        return (new MailService())->send(
            $d['email'],
            'Your CareSync login details',
            $body,
            'Login details for ' . $d['username'] . ' <' . $d['email'] . '>: password ' . $d['password']
        );
    }

    private function pick(string $set): string
    {
        return $set[random_int(0, strlen($set) - 1)];
    }
}
