<?php

namespace App\Services;

use CodeIgniter\Log\Logger;

/**
 * ErrorHandlingService
 * 
 * Centralized error handling for registration and payment workflows
 * Provides consistent error formatting, logging, and user-friendly messages
 * 
 * Usage:
 * $errorService = new ErrorHandlingService();
 * $errorService->logAndNotify('user_id', 'Registration failed', 'database_error', $exception);
 * return $errorService->getLastError(); // Returns formatted error message
 */
class ErrorHandlingService
{
    private ?string $lastError = null;
    private ?string $lastErrorCode = null;
    private Logger $logger;

    public function __construct()
    {
        $this->logger = service('logger');
    }

    /**
     * Get user-friendly error message based on error code
     */
    public function getUserFriendlyMessage(string $errorCode, array $context = []): string
    {
        $messages = [
            'validation_email' => 'Please enter a valid email address.',
            'validation_email_exists' => 'This email address is already registered.',
            'validation_username' => 'Username must be 4-50 characters and contain only letters, numbers, and underscores.',
            'validation_username_exists' => 'This username is already taken. Please choose another.',
            'validation_password' => 'Password must be at least 8 characters and contain 3 of the following: uppercase letters, lowercase letters, numbers, or symbols.',
            'validation_age' => 'Please enter a valid birth date.',
            'validation_age_future' => 'Birth date cannot be in the future.',
            'validation_age_invalid_range' => 'Age must be between 0 and 150 years old.',
            'validation_beneficiary' => 'Please provide valid beneficiary information.',
            'validation_beneficiary_relationship' => 'Please select a valid relationship type.',
            'duplicate_plan_holder' => 'This user is already registered as a plan holder.',
            'duplicate_registration' => 'You are already registered in this system.',
            'user_not_found' => 'User account not found. Please log in again.',
            'plan_not_found' => 'Plan information not found. Please contact support.',
            'branch_not_found' => 'Branch information not found. Please select a valid branch.',
            'database_error' => 'A database error occurred. Please try again later.',
            'transaction_error' => 'Registration failed due to a system error. Please try again.',
            'payment_error' => 'Payment processing failed. Please try again.',
            'gcash_duplicate' => 'This GCash reference number has already been used.',
            'gcash_invalid' => 'Invalid GCash reference number format.',
            'unauthorized' => 'You do not have permission to perform this action.',
            'session_expired' => 'Your session has expired. Please log in again.',
            'unknown_error' => 'An unexpected error occurred. Please try again later.',
        ];

        return $messages[$errorCode] ?? $messages['unknown_error'];
    }

    /**
     * Log error and optionally notify user
     */
    public function logAndNotify(
        int $userId,
        string $context,
        string $errorCode,
        ?\Throwable $exception = null,
        bool $notifyUser = true
    ): void {
        $this->lastErrorCode = $errorCode;
        $this->lastError = $this->getUserFriendlyMessage($errorCode);

        $logMessage = sprintf(
            '[%s] User: %d, Error: %s, Code: %s',
            $context,
            $userId,
            $exception ? $exception->getMessage() : $errorCode,
            $errorCode
        );

        if ($exception) {
            $this->logger->error($logMessage . ' Stack: ' . $exception->getTraceAsString());
        } else {
            $this->logger->error($logMessage);
        }

        if ($notifyUser && $userId > 0) {
            try {
                (new NotificationService())->notify(
                    $userId,
                    $this->lastError,
                    'error_notification',
                    ['error_code' => $errorCode]
                );
            } catch (\Throwable $e) {
                $this->logger->error('Failed to send error notification: ' . $e->getMessage());
            }
        }
    }

    /**
     * Get last error message
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Get last error code
     */
    public function getLastErrorCode(): ?string
    {
        return $this->lastErrorCode;
    }

    /**
     * Validate email with detailed error codes
     */
    public function validateEmail(string $email): array
    {
        if (empty($email)) {
            return ['valid' => false, 'error_code' => 'validation_email'];
        }

        if (strlen($email) > 100) {
            return ['valid' => false, 'error_code' => 'validation_email'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'error_code' => 'validation_email'];
        }

        return ['valid' => true];
    }

    /**
     * Validate password complexity
     */
    public function validatePassword(string $password): array
    {
        if (strlen($password) < 8) {
            return ['valid' => false, 'error_code' => 'validation_password'];
        }

        $complexityCount = 0;
        if (preg_match('/[A-Z]/', $password)) $complexityCount++;
        if (preg_match('/[a-z]/', $password)) $complexityCount++;
        if (preg_match('/[0-9]/', $password)) $complexityCount++;
        if (preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\/\\|`~]/', $password)) $complexityCount++;

        if ($complexityCount < 3) {
            return ['valid' => false, 'error_code' => 'validation_password'];
        }

        return ['valid' => true];
    }

    /**
     * Validate age with detailed error codes
     */
    public function validateAge(string $birthdate): array
    {
        if (empty($birthdate)) {
            return ['valid' => false, 'error_code' => 'validation_age'];
        }

        // Validate format YYYY-MM-DD
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthdate)) {
            return ['valid' => false, 'error_code' => 'validation_age'];
        }

        $birthDate = \DateTime::createFromFormat('Y-m-d', $birthdate);
        if (!$birthDate || $birthDate->format('Y-m-d') !== $birthdate) {
            return ['valid' => false, 'error_code' => 'validation_age'];
        }

        $today = new \DateTime();
        if ($birthDate > $today) {
            return ['valid' => false, 'error_code' => 'validation_age_future'];
        }

        $age = $today->diff($birthDate)->y;
        if ($age < 0 || $age > 150) {
            return ['valid' => false, 'error_code' => 'validation_age_invalid_range'];
        }

        return ['valid' => true, 'age' => $age];
    }

    /**
     * Validate beneficiary information
     */
    public function validateBeneficiary(array $beneficiary): array
    {
        $firstName = trim($beneficiary['first_name'] ?? '');
        $lastName = trim($beneficiary['last_name'] ?? '');
        $relationship = trim($beneficiary['relationship'] ?? '');

        if (empty($firstName) || empty($lastName) || empty($relationship)) {
            return ['valid' => false, 'error_code' => 'validation_beneficiary'];
        }

        // Fix Prompts Task 6: relationship is now a dropdown of real labels
        // (config('Beneficiary')), not a fixed spouse/child/parent/sibling/
        // other set - 'Other' still allows free text.
        $allowedRelationships = config(\Config\Beneficiary::class)->relationships;
        if (!array_key_exists($relationship, $allowedRelationships) && strtolower($relationship) !== 'other') {
            return ['valid' => false, 'error_code' => 'validation_beneficiary_relationship'];
        }

        return ['valid' => true];
    }

    /**
     * Format validation errors for display
     */
    public function formatValidationErrors(array $errors): array
    {
        $formatted = [];

        foreach ($errors as $field => $fieldErrors) {
            if (is_array($fieldErrors)) {
                $formatted[$field] = array_map(function ($error) {
                    return $error; // Already human-readable from validator
                }, $fieldErrors);
            } else {
                $formatted[$field] = [$fieldErrors];
            }
        }

        return $formatted;
    }

    /**
     * Create error response with status and message
     */
    public function createErrorResponse(string $message, string $errorCode, $data = null): array
    {
        return [
            'success' => false,
            'message' => $message,
            'error_code' => $errorCode,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Create success response
     */
    public function createSuccessResponse(string $message, $data = null): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }
}
