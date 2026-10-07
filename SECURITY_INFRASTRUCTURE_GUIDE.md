# Security & Audit Infrastructure - Implementation Guide

## Overview

The CareSync/KaaGapay system now includes a comprehensive security and audit infrastructure consisting of:

1. **Four CodeIgniter Services** for centralized security management
2. **Five Security Tables** in the database
3. **Database Triggers** for automatic audit logging
4. **System Settings Management** with caching
5. **Complete Migration Files** for CI4 database management

---

## Database Infrastructure

### Tables Created

#### 1. **audit_logs**
Tracks all changes (INSERT, UPDATE, DELETE) to records across the system.

**Columns:**
- `log_id` - Primary key
- `table_name` - Name of modified table
- `record_id` - ID of modified record
- `action` - INSERT, UPDATE, or DELETE
- `old_values` - JSON of previous values (for UPDATE/DELETE)
- `new_values` - JSON of new values (for INSERT/UPDATE)
- `changed_by` - User ID making the change
- `ip_address` - IP address of requester
- `description` - Human-readable description
- `changed_at` - Timestamp of change

**Indexes:**
- Primary key on `log_id`
- Composite index on `(table_name, record_id)`
- Index on `changed_at` for time-based queries
- Foreign key to `users(user_id)`

---

#### 2. **payment_transactions**
Tracks payment status transitions for audit trail.

**Columns:**
- `transaction_id` - Primary key
- `payment_id` - Reference to payment
- `old_status` - Previous status
- `new_status` - New status
- `reason` - Reason for status change
- `changed_by` - User ID making the change
- `ip_address` - IP address of requester
- `transitioned_at` - Timestamp

**Indexes:**
- Primary key on `transaction_id`
- Index on `payment_id`
- Index on `transitioned_at`
- Foreign key to `payments(payment_id)` with CASCADE

**Automatic Logging:**
- Database trigger `trg_payment_status_change` automatically logs all payment status changes

---

#### 3. **service_logs**
Tracks service status transitions for audit trail.

**Columns:**
- `log_id` - Primary key
- `service_id` - Reference to service
- `old_status` - Previous status
- `new_status` - New status
- `notes` - Additional notes
- `changed_by` - User ID making the change
- `ip_address` - IP address of requester
- `logged_at` - Timestamp

**Indexes:**
- Primary key on `log_id`
- Index on `service_id`
- Index on `logged_at`
- Foreign key to `services(service_id)` with CASCADE

**Automatic Logging:**
- Database trigger `trg_service_status_change` automatically logs all service status changes

---

#### 4. **email_logs**
Tracks email delivery attempts for debugging and audit.

**Columns:**
- `email_log_id` - Primary key
- `recipient` - Email address
- `subject` - Email subject
- `status` - 'sent', 'failed', or 'bounced'
- `error_message` - Error details if failed
- `user_id` - Related user ID
- `sent_at` - Timestamp

**Indexes:**
- Primary key on `email_log_id`
- Index on `recipient`
- Index on `status`
- Index on `sent_at`
- Foreign key to `users(user_id)` with SET NULL

---

#### 5. **rate_limits**
Manages API and login rate limiting with IP-based blocking.

**Columns:**
- `limit_id` - Primary key
- `ip_address` - IP address
- `action` - Action type (e.g., 'login', 'api_call')
- `attempt_count` - Number of attempts
- `first_attempt` - First attempt timestamp
- `last_attempt` - Most recent attempt timestamp
- `is_blocked` - Whether IP is blocked
- `blocked_until` - Block expiration time

**Indexes:**
- Primary key on `limit_id`
- Unique index on `(ip_address, action)`
- Index on `is_blocked`
- Index on `blocked_until`

**Features:**
- Automatic attempt counting
- Time-based blocking with expiration
- Configurable lockout duration

---

#### 6. **user_sessions**
Manages active user sessions with expiration tracking.

**Columns:**
- `session_id` - Primary key
- `user_id` - Reference to user
- `session_token` - Unique session identifier
- `ip_address` - IP address of session
- `user_agent` - Browser/client information
- `created_at` - Session creation time
- `last_activity` - Last activity timestamp
- `expires_at` - Session expiration time
- `is_active` - Active status flag

**Indexes:**
- Primary key on `session_id`
- Composite index on `(user_id, session_token)`
- Index on `expires_at`
- Index on `is_active`
- Foreign key to `users(user_id)` with CASCADE

---

#### 7. **api_keys**
Manages API authentication with IP whitelisting.

**Columns:**
- `key_id` - Primary key
- `user_id` - Reference to user
- `api_key` - Hashed API key (SHA256)
- `api_secret` - Hashed API secret (SHA256)
- `name` - Descriptive name
- `last_used` - Last usage timestamp
- `created_at` - Creation timestamp
- `expires_at` - Expiration date
- `is_active` - Active status
- `ip_whitelist` - JSON array of allowed IPs

**Indexes:**
- Primary key on `key_id`
- Unique index on `api_key`
- Index on `user_id`
- Index on `is_active`
- Foreign key to `users(user_id)` with CASCADE

---

#### 8. **system_settings**
Centralized system configuration management.

**Columns:**
- `setting_id` - Primary key
- `setting_key` - Unique setting key
- `setting_value` - Setting value (stored as string)
- `category` - Setting category (payment, service, security, system)
- `data_type` - Value type (string, integer, boolean, json, decimal)
- `description` - Human-readable description
- `is_active` - Active status
- `created_at` - Creation timestamp
- `updated_at` - Last update timestamp

**Indexes:**
- Primary key on `setting_id`
- Unique index on `setting_key`
- Index on `category`
- Index on `is_active`

**Default Settings:**
- **Payment:** minimum_payment, maximum_advance_months, delinquent_threshold_months, payment_reminder_days
- **Service:** service_advance_notice_days, service_cancellation_deadline_hours
- **Security:** password_expiry_days, session_timeout_minutes, max_login_attempts, account_lockout_minutes
- **System:** timezone, currency, company_name, support_email, app_version

**Features:**
- Redis caching with 1-hour TTL
- Automatic type casting based on data_type
- Change tracking via audit_logs
- Category-based retrieval

---

#### 9. **Users Table Enhancements**
New security columns added to `users` table:

**New Columns:**
- `failed_login_attempts` INT - Count of failed login attempts
- `last_failed_login` TIMESTAMP - Last failed login time
- `locked_until` TIMESTAMP - Account lockout expiration
- `two_factor_enabled` TINYINT - 2FA flag
- `two_factor_secret` VARCHAR(255) - 2FA secret for TOTP
- `ip_address_created` VARCHAR(45) - IP where account was created
- `ip_address_last_login` VARCHAR(45) - IP of last login

**Indexes:**
- Index on `locked_until` for lockout queries
- Index on `two_factor_enabled` for 2FA status

---

## CodeIgniter Services

### 1. AuditService

**Location:** `app/Services/AuditService.php`

**Purpose:** Centralized audit logging for all data changes.

**Key Methods:**

#### `logChange()`
```php
public function logChange(
    string $tableName,
    $recordId,
    string $action,
    ?array $oldValues = null,
    ?array $newValues = null,
    ?int $userId = null,
    ?string $ipAddress = null,
    ?string $description = null
): bool
```
Logs INSERT, UPDATE, DELETE operations.

**Example:**
```php
$auditService = new AuditService();
$auditService->logChange(
    'plans',
    123,
    'UPDATE',
    ['status' => 'active'],
    ['status' => 'inactive'],
    auth()->id(),
    $_SERVER['REMOTE_ADDR'],
    'Plan deactivated'
);
```

#### `logPaymentTransition()`
```php
public function logPaymentTransition(
    int $paymentId,
    string $oldStatus,
    string $newStatus,
    ?string $reason = null,
    ?int $userId = null,
    ?string $ipAddress = null
): bool
```
Logs payment status changes with automatic trigger support.

#### `logServiceTransition()`
```php
public function logServiceTransition(
    int $serviceId,
    string $oldStatus,
    string $newStatus,
    ?string $notes = null,
    ?int $userId = null,
    ?string $ipAddress = null
): bool
```
Logs service status changes with automatic trigger support.

#### `logEmailDelivery()`
```php
public function logEmailDelivery(
    string $recipient,
    string $subject,
    string $status,
    ?string $errorMessage = null,
    ?int $userId = null
): bool
```
Logs email delivery attempts.

#### `getRecordHistory()`
```php
public function getRecordHistory(string $tableName, $recordId, int $limit = 50): array
```
Retrieves complete change history for a specific record.

#### `getUserActivity()`
```php
public function getUserActivity(int $userId, int $limit = 100): array
```
Retrieves all changes made by a specific user.

#### `getAuditsByDateRange()`
```php
public function getAuditsByDateRange(
    string $startDate,
    string $endDate,
    ?string $tableName = null,
    int $limit = 200
): array
```
Retrieves audit logs for a date range with optional table filter.

#### `cleanupOldLogs()`
```php
public function cleanupOldLogs(int $daysToKeep = 90): int
```
Deletes audit logs older than specified days (retention policy).

---

### 2. SettingsService

**Location:** `app/Services/SettingsService.php`

**Purpose:** Centralized system configuration with caching and type casting.

**Key Methods:**

#### `get()`
```php
public function get(string $key, $default = null)
```
Retrieves a single setting value with automatic type casting.

**Example:**
```php
$settingsService = new SettingsService();
$timeout = $settingsService->get('session_timeout_minutes', 30);
// Returns: (int) 30
```

#### `getAll()`
```php
public function getAll(): array
```
Retrieves all active settings with automatic caching (1 hour TTL).

#### `getByCategory()`
```php
public function getByCategory(string $category): array
```
Retrieves all settings in a category (payment, service, security, system).

**Example:**
```php
$securitySettings = $settingsService->getByCategory('security');
// Returns array of all security-related settings
```

#### `set()`
```php
public function set(
    string $key,
    $value,
    ?int $userId = null,
    ?string $ipAddress = null
): bool
```
Creates or updates a setting with automatic audit logging and cache invalidation.

**Example:**
```php
$settingsService->set(
    'max_login_attempts',
    3,
    auth()->id(),
    $_SERVER['REMOTE_ADDR']
);
```

#### `setMultiple()`
```php
public function setMultiple(
    array $settings,
    ?int $userId = null,
    ?string $ipAddress = null
): int
```
Sets multiple settings at once.

#### `delete()`
```php
public function delete(
    string $key,
    ?int $userId = null,
    ?string $ipAddress = null
): bool
```
Deletes a setting with audit logging.

#### `reloadCache()`
```php
public function reloadCache(): void
```
Force reload of settings cache.

**Type Casting:**
- `integer` - Cast to (int)
- `boolean` - Cast to boolean (1/true = true, 0/false = false)
- `json` - json_decode to array
- `decimal` - Cast to (float)
- `string` - Default string value

---

### 3. SecurityService

**Location:** `app/Services/SecurityService.php`

**Purpose:** Rate limiting, session management, and security operations.

**Key Methods:**

#### `isRateLimited()`
```php
public function isRateLimited(string $ipAddress, string $action = 'login'): bool
```
Checks if IP is rate limited for an action with automatic expiration.

**Example:**
```php
$securityService = new SecurityService();
if ($securityService->isRateLimited($_SERVER['REMOTE_ADDR'], 'login')) {
    return redirect()->back()->with('error', 'Too many login attempts');
}
```

#### `recordAttempt()`
```php
public function recordAttempt(
    string $ipAddress,
    string $action = 'login',
    int $maxAttempts = 5,
    int $lockoutMinutes = 15
): int
```
Records an attempt and returns current count. Automatically locks after max attempts.

**Example:**
```php
if ($loginFailed) {
    $attempts = $securityService->recordAttempt(
        $_SERVER['REMOTE_ADDR'],
        'login',
        5,
        15
    );
    
    if ($attempts >= 5) {
        // Account locked
        return redirect()->back()->with('error', 'Account locked due to too many failed attempts');
    }
}
```

#### `resetAttempts()`
```php
public function resetAttempts(string $ipAddress, string $action = 'login'): bool
```
Resets attempt counter after successful login.

#### `createSession()`
```php
public function createSession(
    int $userId,
    string $ipAddress,
    ?string $userAgent = null
): string|false
```
Creates a new user session and returns session token.

**Example:**
```php
$token = $securityService->createSession(
    $user->user_id,
    $_SERVER['REMOTE_ADDR'],
    $_SERVER['HTTP_USER_AGENT']
);

session()->set(['user_id' => $user->user_id, 'token' => $token]);
```

#### `validateSession()`
```php
public function validateSession(string $sessionToken, ?string $ipAddress = null)
```
Validates session token and returns session data or false if invalid/expired.

#### `expireSession()`
```php
public function expireSession(string $sessionToken): bool
```
Expires a specific session (logout).

#### `cleanupExpiredSessions()`
```php
public function cleanupExpiredSessions(): int
```
Removes all expired sessions (should be run periodically).

#### `getUserSessions()`
```php
public function getUserSessions(int $userId): array
```
Returns all active sessions for a user.

#### `expireAllUserSessions()`
```php
public function expireAllUserSessions(int $userId): int
```
Expires all sessions for a user (e.g., after password change).

#### `blockIP()`
```php
public function blockIP(string $ipAddress, string $action = 'login', int $minutes = 60): bool
```
Manually block an IP address.

#### `unblockIP()`
```php
public function unblockIP(string $ipAddress, string $action = 'login'): bool
```
Manually unblock an IP address.

---

### 4. APIKeyService

**Location:** `app/Services/APIKeyService.php`

**Purpose:** API key management with authentication and IP whitelisting.

**Key Methods:**

#### `generateKey()`
```php
public function generateKey(
    int $userId,
    ?string $name = null,
    ?string $expiresAt = null,
    ?array $ipWhitelist = null
)
```
Generates a new API key and secret pair (cryptographically secure).

**Returns:** `[key_id, api_key, api_secret]` - Only return unhashed values once!

**Example:**
```php
$apiService = new APIKeyService();
$result = $apiService->generateKey(
    auth()->id(),
    'Mobile App Key',
    date('Y-m-d', strtotime('+1 year')),
    ['192.168.1.1', '10.0.0.1']
);

// $result['api_key'] - show to user only once
// $result['api_secret'] - show to user only once
```

#### `validateKey()`
```php
public function validateKey(
    string $apiKey,
    string $apiSecret,
    ?string $ipAddress = null
)
```
Validates an API key/secret pair with automatic rate limiting.

**Returns:** Key data if valid, false otherwise.

**Example:**
```php
$keyData = $apiService->validateKey(
    $request->getHeader('X-API-Key'),
    $request->getHeader('X-API-Secret'),
    $_SERVER['REMOTE_ADDR']
);

if (!$keyData) {
    return $this->respond(['error' => 'Invalid API credentials'], 401);
}
```

#### `getUserKeys()`
```php
public function getUserKeys(int $userId, bool $activeOnly = true): array
```
Gets all API keys for a user (without secrets).

#### `revokeKey()`
```php
public function revokeKey(
    int $keyId,
    ?int $userId = null,
    ?string $reason = null
): bool
```
Deactivates an API key with audit logging.

#### `updateIPWhitelist()`
```php
public function updateIPWhitelist(
    int $keyId,
    array $ipAddresses,
    ?int $userId = null
): bool
```
Updates IP whitelist for a key.

#### `cleanupExpiredKeys()`
```php
public function cleanupExpiredKeys(): int
```
Deactivates all expired keys (run periodically).

#### `getKeyStatistics()`
```php
public function getKeyStatistics(int $keyId): array
```
Returns usage statistics for a key.

---

## Integration Examples

### Example 1: Login Controller with Security

```php
<?php
namespace App\Controllers;

use App\Services\SecurityService;
use App\Services\AuditService;

class AuthController extends BaseController
{
    public function login()
    {
        $securityService = new SecurityService();
        $auditService = new AuditService();
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        
        // Check rate limiting
        if ($securityService->isRateLimited($ipAddress, 'login')) {
            return redirect()->back()->with('error', 'Too many login attempts. Please try again later.');
        }
        
        $email = $this->request->getPost('email');
        $password = $this->request->getPost('password');
        
        // Verify credentials
        $user = userModel()->where('email', $email)->first();
        
        if (!$user || !password_verify($password, $user->password_hash)) {
            // Record failed attempt
            $securityService->recordAttempt($ipAddress, 'login', 5, 15);
            $auditService->logChange('users', $user->user_id ?? 0, 'UPDATE',
                ['failed_login_attempts' => $user->failed_login_attempts ?? 0],
                ['failed_login_attempts' => ($user->failed_login_attempts ?? 0) + 1],
                null,
                $ipAddress,
                'Failed login attempt'
            );
            return redirect()->back()->with('error', 'Invalid credentials');
        }
        
        // Check if account is locked
        if ($user->locked_until && strtotime($user->locked_until) > time()) {
            return redirect()->back()->with('error', 'Account is temporarily locked');
        }
        
        // Reset attempts after successful login
        $securityService->resetAttempts($ipAddress, 'login');
        
        // Create session
        $token = $securityService->createSession($user->user_id, $ipAddress, $_SERVER['HTTP_USER_AGENT']);
        
        // Update last login
        userModel()->update($user->user_id, [
            'last_login' => date('Y-m-d H:i:s'),
            'ip_address_last_login' => $ipAddress,
            'failed_login_attempts' => 0,
        ]);
        
        // Log successful login
        $auditService->logChange('users', $user->user_id, 'UPDATE',
            ['last_login' => $user->last_login],
            ['last_login' => date('Y-m-d H:i:s')],
            $user->user_id,
            $ipAddress,
            'Successful login'
        );
        
        session()->set(['user_id' => $user->user_id, 'token' => $token]);
        return redirect()->to('/dashboard');
    }
}
```

### Example 2: Settings Management

```php
<?php
namespace App\Controllers\Admin;

use App\Services\SettingsService;

class SettingsController extends BaseController
{
    public function index()
    {
        $settingsService = new SettingsService();
        
        // Get all settings grouped by category
        $data['payment_settings'] = $settingsService->getPaymentSettings();
        $data['security_settings'] = $settingsService->getSecuritySettings();
        $data['system_settings'] = $settingsService->getSystemSettings();
        
        return view('admin/settings', $data);
    }
    
    public function update()
    {
        $settingsService = new SettingsService();
        
        $updates = $this->request->getPost('settings');
        $count = $settingsService->setMultiple(
            $updates,
            auth()->id(),
            $_SERVER['REMOTE_ADDR']
        );
        
        return redirect()->back()->with('success', "{$count} settings updated");
    }
}
```

### Example 3: API Key Middleware

```php
<?php
namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Services\APIKeyService;

class APIKeyAuth implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $apiService = new APIKeyService();
        
        $apiKey = $request->getHeader('X-API-Key')?->getValue();
        $apiSecret = $request->getHeader('X-API-Secret')?->getValue();
        
        if (!$apiKey || !$apiSecret) {
            return response()->setJSON(['error' => 'Missing API credentials'], 401);
        }
        
        $keyData = $apiService->validateKey(
            $apiKey,
            $apiSecret,
            $_SERVER['REMOTE_ADDR']
        );
        
        if (!$keyData) {
            return response()->setJSON(['error' => 'Invalid API credentials'], 401);
        }
        
        // Store key data in request for later use
        $request->api_key_data = $keyData;
    }
    
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
```

---

## Database Migrations

Two migration files have been created:

### 1. CreateAuditInfrastructure
**File:** `app/Database/Migrations/2026-05-10-120000_CreateAuditInfrastructure.php`

Creates:
- `audit_logs` table
- `payment_transactions` table
- `service_logs` table
- `email_logs` table

### 2. CreateSecurityTables
**File:** `app/Database/Migrations/2026-05-10-130000_CreateSecurityTables.php`

Creates:
- `system_settings` table
- `rate_limits` table
- `user_sessions` table
- `api_keys` table
- Adds security columns to `users` table

**Run Migrations:**
```bash
php spark migrate
```

**Rollback:**
```bash
php spark migrate:rollback
```

---

## Database Seeder

**File:** `app/Database/Seeds/SystemSettingsSeeder.php`

Populates `system_settings` with 21 default settings:

**Payment Settings (5):**
- minimum_payment: 240.00
- maximum_advance_months: 12
- delinquent_threshold_months: 3
- payment_reminder_days: 5
- payment_gateway: stripe

**Service Settings (2):**
- service_advance_notice_days: 7
- service_cancellation_deadline_hours: 24

**Security Settings (6):**
- password_expiry_days: 90
- session_timeout_minutes: 30
- max_login_attempts: 5
- account_lockout_minutes: 15
- enable_two_factor: 0
- api_rate_limit_requests: 1000

**System Settings (8):**
- timezone: Asia/Manila
- currency: ₱
- company_name: KaaGapay
- support_email: support@kaagapay.com
- notification_retention_days: 30
- audit_log_retention_days: 90
- enable_maintenance_mode: 0
- app_version: 1.0.0

**Run Seeder:**
```bash
php spark db:seed SystemSettingsSeeder
```

---

## Database Triggers

Two triggers have been created for automatic audit logging:

### 1. `trg_payment_status_change`
Automatically logs payment status changes to `payment_transactions` table.

### 2. `trg_service_status_change`
Automatically logs service status changes to `service_logs` table.

**These triggers ensure:**
- No manual logging required for status changes
- Complete audit trail automatically maintained
- Consistent timestamp recording
- Automatic integration with audit system

---

## Best Practices

### 1. Always Use Services
```php
// Good
$auditService = new AuditService();
$auditService->logChange(...);

// Avoid direct database access
DB::table('audit_logs')->insert([...]);
```

### 2. Include IP and User Context
```php
$auditService->logChange(
    'plans',
    $recordId,
    'UPDATE',
    $oldValues,
    $newValues,
    auth()->id(),          // Always include user
    $_SERVER['REMOTE_ADDR'] // Always include IP
);
```

### 3. Cache Settings Appropriately
```php
// Good - uses cache
$timeout = $settingsService->get('session_timeout_minutes');

// When settings change
$settingsService->set('session_timeout_minutes', 45);
// Cache is automatically invalidated
```

### 4. Cleanup Old Data Periodically
```php
// Run in scheduled task (CLI command)
$auditService->cleanupOldLogs(90);
$securityService->cleanupExpiredSessions();
$apiService->cleanupExpiredKeys();
```

### 5. API Key Security
```php
// Good - show keys only once
$keys = $apiService->generateKey($userId, 'My App');
// Display $keys['api_key'] and $keys['api_secret'] to user
// Never store unhashed versions

// Good - validate every API request
$keyData = $apiService->validateKey($key, $secret, $_SERVER['REMOTE_ADDR']);
```

---

## Verification

All components have been verified:

✅ **Database Tables:** 9 tables created successfully
✅ **Indexes:** All performance indexes created
✅ **Foreign Keys:** All relationships established
✅ **Triggers:** Both status change triggers active
✅ **Services:** 4 services implemented
✅ **Migrations:** 2 migration files created
✅ **Seeder:** Default settings seeded (21 rows)
✅ **Security Columns:** Users table enhanced with 7 new columns

---

## File Locations

| Component | Path |
|-----------|------|
| AuditService | `ci4/app/Services/AuditService.php` |
| SettingsService | `ci4/app/Services/SettingsService.php` |
| SecurityService | `ci4/app/Services/SecurityService.php` |
| APIKeyService | `ci4/app/Services/APIKeyService.php` |
| Audit Migration | `ci4/app/Database/Migrations/2026-05-10-120000_CreateAuditInfrastructure.php` |
| Security Migration | `ci4/app/Database/Migrations/2026-05-10-130000_CreateSecurityTables.php` |
| Settings Seeder | `ci4/app/Database/Seeds/SystemSettingsSeeder.php` |

---

## Next Steps

1. **Integrate into Controllers** - Add service calls to existing controllers
2. **Create CLI Commands** - For cleanup tasks and maintenance
3. **Add Tests** - Unit tests for each service
4. **Deploy Migrations** - Run migrations in production
5. **Monitor Logs** - Check audit_logs regularly for security events
6. **Tune Settings** - Adjust security settings based on usage patterns

