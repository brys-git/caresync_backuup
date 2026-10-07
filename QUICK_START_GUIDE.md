# Quick Start Guide - Security Infrastructure

## 5-Minute Setup

### Step 1: Import Services in Your Controller
```php
<?php
namespace App\Controllers;

use App\Services\AuditService;
use App\Services\SettingsService;
use App\Services\SecurityService;
use App\Services\APIKeyService;

class MyController extends BaseController
{
    // ...
}
```

### Step 2: Use in Your Code

#### Track Changes
```php
$auditService = new AuditService();
$auditService->logChange(
    'plans',
    $plan->id,
    'UPDATE',
    $oldData,
    $newData,
    auth()->id(),
    $_SERVER['REMOTE_ADDR']
);
```

#### Get Settings
```php
$settingsService = new SettingsService();
$timeout = $settingsService->get('session_timeout_minutes');
$allSettings = $settingsService->getAll();
```

#### Check Rate Limiting
```php
$securityService = new SecurityService();
if ($securityService->isRateLimited($_SERVER['REMOTE_ADDR'], 'login')) {
    return 'Too many attempts';
}
```

#### Validate API Keys
```php
$apiService = new APIKeyService();
$keyData = $apiService->validateKey($apiKey, $apiSecret, $_SERVER['REMOTE_ADDR']);
if (!$keyData) {
    return response()->setJSON(['error' => 'Invalid key'], 401);
}
```

---

## Common Tasks

### Logging a Payment Status Change
```php
// Manual logging (also auto-logged by trigger)
$auditService = new AuditService();
$auditService->logPaymentTransition(
    $payment->id,
    'pending',
    'completed',
    'Payment received',
    auth()->id(),
    $_SERVER['REMOTE_ADDR']
);
```

### Implementing Secure Login
```php
$securityService = new SecurityService();

// 1. Check rate limit
if ($securityService->isRateLimited($ip, 'login')) {
    return 'Locked. Try again in 15 minutes';
}

// 2. Verify credentials
if (!passwordVerify($password, $user->password_hash)) {
    $securityService->recordAttempt($ip, 'login', 5, 15);
    return 'Invalid credentials';
}

// 3. Reset attempts
$securityService->resetAttempts($ip, 'login');

// 4. Create session
$token = $securityService->createSession($user->id, $ip);
session()->set(['user_id' => $user->id, 'token' => $token]);
```

### Generating API Keys for Users
```php
$apiService = new APIKeyService();

$result = $apiService->generateKey(
    $user->id,
    'Mobile App',
    date('Y-m-d', strtotime('+1 year')),
    ['192.168.1.1']
);

// Show to user ONLY once:
echo "API Key: " . $result['api_key'];
echo "API Secret: " . $result['api_secret'];
```

### Creating a Settings Panel
```php
$settingsService = new SettingsService();

// Get all payment settings
$paymentSettings = $settingsService->getByCategory('payment');

// Update multiple settings
$updates = [
    'minimum_payment' => 500,
    'max_login_attempts' => 3,
];

$settingsService->setMultiple($updates, auth()->id());
```

---

## File Locations

```
ci4/
├── app/
│   ├── Services/
│   │   ├── AuditService.php ................... Change tracking
│   │   ├── SettingsService.php ............... Configuration
│   │   ├── SecurityService.php ............... Rate limiting & sessions
│   │   └── APIKeyService.php ................. API authentication
│   │
│   └── Database/
│       ├── Migrations/
│       │   ├── 2026-05-10-120000_CreateAuditInfrastructure.php
│       │   └── 2026-05-10-130000_CreateSecurityTables.php
│       │
│       └── Seeds/
│           └── SystemSettingsSeeder.php
│
└── ci4/
    └── SECURITY_INFRASTRUCTURE_GUIDE.md ....... Full documentation
```

---

## Database Tables Quick Reference

### View Audit Trail
```sql
SELECT * FROM audit_logs 
WHERE table_name = 'plans' AND record_id = 123
ORDER BY changed_at DESC;
```

### View Failed Logins
```sql
SELECT * FROM rate_limits 
WHERE action = 'login' AND is_blocked = 1;
```

### View Active Sessions
```sql
SELECT * FROM user_sessions 
WHERE is_active = 1 AND expires_at > NOW();
```

### View API Keys for User
```sql
SELECT * FROM api_keys 
WHERE user_id = 5 AND is_active = 1;
```

### View System Settings
```sql
SELECT setting_key, setting_value 
FROM system_settings 
WHERE is_active = 1;
```

---

## Common Settings

```php
$settingsService = new SettingsService();

// Payment settings
$settingsService->get('minimum_payment');           // 240.00
$settingsService->get('maximum_advance_months');   // 12
$settingsService->get('payment_reminder_days');    // 5

// Security settings
$settingsService->get('session_timeout_minutes');  // 30
$settingsService->get('max_login_attempts');       // 5
$settingsService->get('password_expiry_days');     // 90

// System settings
$settingsService->get('company_name');             // KaaGapay
$settingsService->get('timezone');                 // Asia/Manila
$settingsService->get('support_email');            // support@kaagapay.com
```

---

## API Response Examples

### Successful API Call
```json
{
  "status": "success",
  "data": {
    "id": 123,
    "name": "John Doe"
  }
}
```

### Blocked by Rate Limiting
```json
{
  "error": "Too many requests",
  "retry_after": 300,
  "status_code": 429
}
```

### Invalid API Key
```json
{
  "error": "Invalid API credentials",
  "status_code": 401
}
```

### Session Expired
```json
{
  "error": "Session expired",
  "redirect": "/signin",
  "status_code": 401
}
```

---

## Troubleshooting

### Service class not found
```
Error: Class 'App\Services\AuditService' not found

Solution: Ensure file exists and namespace is correct
php -l app/Services/AuditService.php
```

### Database trigger not firing
```
Solution: Verify triggers exist
SHOW TRIGGERS;

Should see:
- trg_payment_status_change
- trg_service_status_change
```

### Rate limit too strict
```php
// Adjust in code
$securityService->recordAttempt($ip, 'login', 10, 20); // 10 attempts, 20 min lockout

// Or adjust in settings
$settingsService->set('max_login_attempts', 10);
$settingsService->set('account_lockout_minutes', 20);
```

### Settings cache stale
```php
// Reload cache
$settingsService->reloadCache();

// Or bypass cache for one call
$all = $settingsService->getAll();
```

---

## Deployment Checklist

- [ ] Services files created in `app/Services/`
- [ ] Migration files in `app/Database/Migrations/`
- [ ] Seeder file in `app/Database/Seeds/`
- [ ] Run migrations: `php spark migrate`
- [ ] Run seeder: `php spark db:seed SystemSettingsSeeder`
- [ ] Verify tables exist: `SHOW TABLES;`
- [ ] Verify triggers exist: `SHOW TRIGGERS;`
- [ ] Test each service in development
- [ ] Update controllers with audit logging
- [ ] Add rate limiting to login controller
- [ ] Add session management to auth flow
- [ ] Deploy to staging
- [ ] Final testing in staging
- [ ] Deploy to production
- [ ] Monitor audit logs
- [ ] Setup email alerts for security events

---

## Performance Tips

1. **Use Settings Service** - Cached for 1 hour, reduces DB queries
2. **Check Rate Limits Early** - Prevent expensive operations
3. **Batch Audit Logs** - Use bulk insert for multiple changes
4. **Clean Old Data** - Regular cleanup prevents table bloat
5. **Index Custom Queries** - Add indexes if querying audit logs frequently

---

## Security Reminders

⚠️ **CRITICAL:**
- Never expose API keys in logs
- Always hash keys before storing
- Always include IP address in audit logs
- Always use SettingsService for configuration
- Never bypass rate limiting
- Always validate sessions
- Always log authentication failures
- Always check user authorization

---

## API Endpoint Example

```php
<?php
namespace App\Controllers\Api;

use App\Services\APIKeyService;
use CodeIgniter\RESTful\ResourceController;

class DataController extends ResourceController
{
    public function index()
    {
        $apiService = new APIKeyService();
        
        // Validate API key
        $apiKey = $this->request->getHeader('X-API-Key')?->getValue();
        $apiSecret = $this->request->getHeader('X-API-Secret')?->getValue();
        
        if (!$apiKey || !$apiSecret) {
            return $this->respond(['error' => 'Missing credentials'], 401);
        }
        
        $keyData = $apiService->validateKey(
            $apiKey, 
            $apiSecret, 
            $_SERVER['REMOTE_ADDR']
        );
        
        if (!$keyData) {
            return $this->respond(['error' => 'Invalid credentials'], 401);
        }
        
        // Proceed with request
        $data = []; // Get your data
        return $this->respond($data, 200);
    }
}
```

---

## More Information

For complete documentation, see:
- **SECURITY_INFRASTRUCTURE_GUIDE.md** - Full API reference
- **Service files** - Code comments and docblocks
- **Database schema** - Run `DESCRIBE [table];`

---

**Ready to use! Start integrating these services into your controllers.** 🚀
