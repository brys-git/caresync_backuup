<?php

namespace App\Services;

use CodeIgniter\Security\Security;

/**
 * SecurityEnhancementService
 * 
 * Handles security-related operations for registration and payment workflows
 * Includes CSRF protection, password hashing, input sanitization, and rate limiting
 * 
 * Usage:
 * $securityService = new SecurityEnhancementService();
 * $securityService->validateCSRFToken($token);
 * $securityService->hashPassword($password);
 * $securityService->sanitizeInput($input);
 */
class SecurityEnhancementService
{
    private Security $security;
    private const RATE_LIMIT_KEY_PREFIX = 'registration_attempt_';
    private const MAX_ATTEMPTS = 5;
    private const ATTEMPT_WINDOW = 3600; // 1 hour in seconds

    public function __construct()
    {
        $this->security = service('security');
    }

    /**
     * Hash password using PHP's password_hash
     */
    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, [
            'cost' => 12, // Higher cost = more secure but slower
        ]);
    }

    /**
     * Verify password against hash
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Check if password needs rehashing (e.g., cost changed)
     */
    public function passwordNeedsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, [
            'cost' => 12,
        ]);
    }

    /**
     * Sanitize user input to prevent injection attacks
     */
    public function sanitizeInput(string $input, string $type = 'text'): string
    {
        $input = trim($input);

        switch ($type) {
            case 'email':
                return filter_var($input, FILTER_SANITIZE_EMAIL);
            case 'number':
                return preg_replace('/[^0-9]/', '', $input);
            case 'alphanum':
                return preg_replace('/[^a-zA-Z0-9_-]/', '', $input);
            case 'text':
            default:
                // Remove potentially dangerous characters
                return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        }
    }

    /**
     * Validate CSRF token
     */
    public function validateCSRFToken(string $token): bool
    {
        try {
            return $this->security->verify() && hash_equals(
                $this->security->getCSRFHash(),
                $token
            );
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Generate CSRF token for forms
     */
    public function getCSRFToken(): string
    {
        return $this->security->getCSRFHash();
    }

    /**
     * Get CSRF token field name
     */
    public function getCSRFTokenName(): string
    {
        return $this->security->getCSRFTokenName();
    }

    /**
     * Check rate limiting for registration attempts
     */
    public function checkRegistrationAttempts(string $identifier): array
    {
        $key = self::RATE_LIMIT_KEY_PREFIX . $identifier;
        $cache = service('cache');

        $attempts = (int) ($cache->get($key) ?? 0);

        if ($attempts >= self::MAX_ATTEMPTS) {
            return [
                'allowed' => false,
                'message' => 'Too many registration attempts. Please try again later.',
                'attempts' => $attempts,
                'max_attempts' => self::MAX_ATTEMPTS,
            ];
        }

        return [
            'allowed' => true,
            'attempts' => $attempts,
            'max_attempts' => self::MAX_ATTEMPTS,
        ];
    }

    /**
     * Record registration attempt
     */
    public function recordRegistrationAttempt(string $identifier): void
    {
        $key = self::RATE_LIMIT_KEY_PREFIX . $identifier;
        $cache = service('cache');

        $attempts = (int) ($cache->get($key) ?? 0);
        $cache->save($key, $attempts + 1, self::ATTEMPT_WINDOW);
    }

    /**
     * Clear registration attempt tracking
     */
    public function clearRegistrationAttempts(string $identifier): void
    {
        $key = self::RATE_LIMIT_KEY_PREFIX . $identifier;
        service('cache')->delete($key);
    }

    /**
     * Validate user role for registration
     */
    public function validateRoleForRegistration(int $roleId): bool
    {
        // Only plan holders (role_id = 4) can self-register
        return $roleId === 4;
    }

    /**
     * Validate admin/branch admin role for registering users
     */
    public function validateAdminRoleForRegistration(int $roleId): bool
    {
        // Admin (1) and BranchAdmin (2) can register users
        return in_array($roleId, [1, 2], true);
    }

    /**
     * Check if username is safe (no reserved words, etc.)
     */
    public function isUsernameReserved(string $username): bool
    {
        $reserved = [
            'admin', 'system', 'api', 'admin-panel', 'management',
            'dashboard', 'settings', 'profile', 'account', 'logout',
            'login', 'register', 'forgot-password', 'reset-password',
        ];

        return in_array(strtolower($username), $reserved, true);
    }

    /**
     * Generate secure random token
     */
    public function generateSecureToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Encrypt sensitive data
     */
    public function encryptData(string $data): string
    {
        $encrypter = service('encrypter');
        return $encrypter->encrypt($data);
    }

    /**
     * Decrypt sensitive data
     */
    public function decryptData(string $data): string
    {
        $encrypter = service('encrypter');
        return $encrypter->decrypt($data);
    }

    /**
     * Log security event
     */
    public function logSecurityEvent(string $event, int $userId, string $details = ''): void
    {
        $logger = service('logger');
        $logMessage = sprintf(
            '[SECURITY] Event: %s, User: %d, Details: %s, IP: %s, Timestamp: %s',
            $event,
            $userId,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN',
            date('Y-m-d H:i:s')
        );

        $logger->info($logMessage);
    }

    /**
     * Detect suspicious activity pattern
     */
    public function detectSuspiciousActivity(int $userId, string $activityType): bool
    {
        $cache = service('cache');
        $key = "suspicious_activity_{$userId}_{$activityType}";

        $count = (int) ($cache->get($key) ?? 0);
        $threshold = 10; // Arbitrary threshold

        if ($count > $threshold) {
            return true;
        }

        $cache->save($key, $count + 1, 300); // 5 minutes window
        return false;
    }
}
