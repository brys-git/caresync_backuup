# KAAGAPAY System Refactoring - Implementation Guide

**Date:** April 28, 2026  
**Phase:** High Priority Refactoring (Weeks 1-2)  
**Status:** 60% Complete

---

## ✅ COMPLETED WORK

### 1. Database Schema Refactoring
**Status:** FULLY COMPLETED ✅

#### Migrations Applied:
1. **2026-04-28-100000_NormalizeServiceListStatus** ✅
   - Documented existing `service_list.status` column (values: active, pending, rejected, inactive)
   
2. **2026-04-28-110000_NormalizePackagesStatus** ✅
   - Added `packages.status` column (ENUM: pending, approved, rejected, inactive)
   - Default: 'approved'
   
3. **2026-04-28-120000_AddPaymentTypeToPayments** ✅
   - Added `payments.payment_type` column (ENUM: initial_registration, monthly_contribution, service_payment, addon_payment)
   - Default: 'monthly_contribution'
   
4. **2026-04-28-130000_CreateActivityLogsTable** ✅
   - Created `activity_logs` table for comprehensive audit trail
   - Columns: log_id, user_id, action, module, target_id, description, old_values, new_values, ip_address, created_at
   - Indexes on: user_id, module, target_id, created_at
   
5. **2026-04-28-140000_AddTypeToNotifications** ✅
   - Added `notifications.type` column (ENUM: payment_approved, payment_rejected, service_approved, service_rejected, registration_pending, service_completed, general)
   - Default: 'general'

#### Verification
```bash
php spark migrate:status
# Output shows all 12 migrations applied successfully
```

---

### 2. Service Layer Architecture
**Status:** FULLY COMPLETED ✅

#### New Service Classes Created:

**A. MembershipService** (`app/Services/MembershipService.php`)
```php
// Core Methods:
- getActivePlan(planHolderId): ?array
  → Returns the active plan for a plan holder
  
- getPlans(planHolderId): array
  → Returns all plans (prioritizing active)
  
- isActiveMember(userId, planHolderId): bool
  → Checks if user is an active member:
    - users.status = 'active'
    - is_plan_holder = 1
    - plans.status = 'active'
  
- enforceOneActivePlan(planHolderId): bool
  → Ensures only ONE active plan per holder
  
- getMembershipDetails(planHolderId): ?array
  → Returns membership info (fee, balance, months_paid, etc.)
```

**B. ApprovalService** (`app/Services/ApprovalService.php`)
```php
// Core Methods:
- approveInitialPayment(paymentId): bool
  → Activates plan and plan holder after payment approval
  
- rejectInitialPayment(paymentId, reason): bool
  → Marks payment as cancelled
  
- approveServiceApplication(applicationId): bool
  → Approves pending service application
  
- approveServiceOffer(type, itemId): bool
  → Approves pending package/service from branch admin
  
- rejectServiceOffer(type, itemId, reason): bool
  → Rejects pending package/service
```

**C. ActivityLogService** (`app/Services/ActivityLogService.php`)
```php
// Core Methods:
- log(userId, action, module, targetId, description, oldValues, newValues): bool
  → Creates audit log entry
  
- getUserLogs(userId, limit): array
  → Gets logs for specific user
  
- getModuleLogs(module, limit): array
  → Gets logs for specific module
  
- getRecordLogs(module, targetId): array
  → Gets complete audit trail for a record
  
- getAllLogs(limit): array
  → Gets all logs (admin only)
```

**D. NotificationService** (`app/Services/NotificationService.php`)
```php
// Core Methods:
- notify(userId, message, type): bool
  → Sends typed notification
  
- getNotifications(userId, type, status): array
  → Retrieves notifications with optional filters
  
- getUnreadCount(userId): int
  → Gets unread notification count
  
- markAsRead(notificationId): bool
  → Marks single notification as read
  
- markAllAsRead(userId): bool
  → Marks all user notifications as read
  
- getGroupedByType(userId): array
  → Returns notifications grouped by type
  
- notifyMultiple(userIds, message, type): int
  → Sends batch notifications
```

#### Helper Functions Created:

**ActivityHelper.php** - Convenient wrapper functions:
```php
log_activity()              // Log an activity
get_activity_logs()         // Get logs for a record
notify_user()              // Send notification
get_user_notifications()   // Get user's notifications
get_unread_notification_count() // Get unread count
get_active_membership()    // Get active plan
is_active_member()         // Check membership status
```

#### Existing Services (Integration Ready)
- PaymentService
- ClientService
- ReportService
- ServiceManagementService
- StaffManagementService
- StaffService

---

## 🚀 NEXT STEPS (In Priority Order)

### Phase 2: Controller Integration (Next - HIGH PRIORITY)

**Status:** Not Started

#### Task List:
```
1. Update ClientPortal.php
   - Use MembershipService for plan selection
   - Use NotificationService for notifications
   - Implement activity logging
   
2. Update Payments.php
   - Use MembershipService for active plan filtering
   - Log payment actions with ActivityLogService
   
3. Update PaymentTracking.php
   - Use MembershipService for plan selection
   - Log approvals/rejections
   
4. Update BranchAdmin/ClientController.php
   - Use ApprovalService for approvals
   - Log all actions
   
5. Update Admin/BranchManagementController.php
   - Use ApprovalService for service/package approval
   - Log all approvals
```

### Phase 3: Activity Logging Integration (MEDIUM PRIORITY)

**Status:** Not Started

#### Add logging to these flows:
- Payment approval/rejection
- Service application approval
- Package/service creation (pending → approved)
- Plan holder registration
- Plan activation

#### Example Implementation:
```php
// In controllers, after actions complete:
log_activity(
    userId: (int) session('user_id'),
    action: 'approved',
    module: 'payment',
    targetId: $paymentId,
    description: 'Approved initial payment',
    oldValues: ['status' => 'pending'],
    newValues: ['status' => 'paid']
);
```

### Phase 4: Advanced Reporting (MEDIUM PRIORITY)

**Status:** Not Started

#### Reports to Add:
1. **Monthly Collections Report**
   - Total payments received
   - Payment breakdown by method (cash/GCash)
   - Branch contribution analysis
   
2. **Member Status Report**
   - Active vs inactive members
   - Members by branch
   - Delinquent account tracking
   
3. **Service Usage Statistics**
   - Most popular services
   - Service completion rate
   - Service revenue tracking
   
4. **Activity Audit Report**
   - All approvals/rejections by date
   - User action history
   - System change log
   
5. **Payment Trends Analysis**
   - Payment collection trends
   - Average payment frequency
   - Outstanding balance tracking

---

## 📊 NEW MEMBERSHIP LOGIC

### Activation Flow (Simplified)

```
STEP 1: Account Creation
├─ users.status = 'active'
└─ is_plan_holder = 0

STEP 2: Registration
├─ is_plan_holder = 1
└─ plans.status = 'pending'

STEP 3: Initial Payment
├─ payments.payment_type = 'initial_registration'
├─ payments.status = 'pending'
└─ plans.status = 'pending' (unchanged)

STEP 4: Payment Approval (Branch Admin)
├─ payments.status = 'paid'
├─ plans.status = 'active'
└─ plan_holders.status = 'active' ← Derived from plans.status

RESULT: Member is ACTIVE when:
├─ users.status = 'active'
├─ is_plan_holder = 1
└─ plans.status = 'active'
```

### Plan Selection Priority (CASE WHEN)

All queries now use:
```sql
ORDER BY CASE WHEN status = 'active' THEN 1 ELSE 2 END ASC,
         plan_id DESC
```

This ensures:
- ✅ Active plans appear first
- ✅ Falls back to most recent plan
- ✅ Consistency across admin/client views

---

## 🔐 Security Hardening

### Backend Filtering Rules

**Staff Access:**
- All staff queries MUST include: `WHERE assigned_staff = current_user_id`
- VERIFIED in: Services.php controller

**Branch Admin Access:**
- All queries MUST include: `WHERE branch_id = session('branch_id')`
- VERIFIED in: Multiple controllers

**Plan Holder Access:**
- Only see own plans, payments, notifications
- ENFORCED via `is_plan_holder` flag + `user_id` checks

---

## 📝 TESTING CHECKLIST

### Before Moving to Phase 2

- [ ] Verify all migrations applied successfully
- [ ] Check activity_logs table structure
- [ ] Verify payment_type column exists and defaults work
- [ ] Test MembershipService.getActivePlan() method
- [ ] Test MembershipService.enforceOneActivePlan() method
- [ ] Test ApprovalService.approveInitialPayment() method
- [ ] Test NotificationService.notify() with different types
- [ ] Test ActivityLogService.log() records correctly
- [ ] Verify helper functions work (log_activity, notify_user, etc.)

### Manual Testing After Phase 2

- [ ] Register new user → Select plan → Submit initial payment → Approve payment
- [ ] Verify activity logs created for each step
- [ ] Verify notifications sent with correct types
- [ ] Check membership details show correctly
- [ ] Verify multiple plans handled correctly (only one active shown)

---

## 🎯 CODE QUALITY STANDARDS

### New Service Classes Follow:
- ✅ Type hints on all parameters and return types
- ✅ Transaction support for critical operations
- ✅ Error handling with try-catch
- ✅ Comprehensive documentation/comments
- ✅ Consistent naming conventions
- ✅ No direct SQL queries (using query builder)

### Activity Logging Standards:
- ✅ Log all approval/rejection actions
- ✅ Include before/after values for updates
- ✅ Record IP address for security audit
- ✅ Timestamp all logs
- ✅ Capture user who performed action

---

## 📈 ARCHITECTURE IMPROVEMENTS

### Before Refactoring
- 6 overlapping status fields
- Logic scattered across controllers
- No audit trail
- No notification classification
- Payment types not distinguished

### After Refactoring
- ✅ Centralized status logic
- ✅ Reusable service layer
- ✅ Complete activity logging
- ✅ Type-classified notifications
- ✅ Distinguished payment types
- ✅ Simplified membership determination

---

## 🚀 FUTURE EXPANSION (LOW PRIORITY)

These refactorings prepare the system for:
- Mobile application (notification types)
- SMS notifications (activity module tracking)
- GCash API integration (payment_type field)
- QR code payments (payment tracking)
- REST API (centralized services)
- Advanced reporting (activity logs)

---

**Next Action:** Begin Phase 2 - Controller Integration
**Estimated Duration:** 1-2 days
**Priority:** HIGH - Completes core refactoring
