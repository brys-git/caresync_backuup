# Client Registration Logic & Process - Complete Documentation

**System**: CareSync Funeral Plan Management System  
**Framework**: CodeIgniter 4  
**Date**: May 12, 2026  

---

## 1. OVERVIEW

The client registration system in CareSync is a multi-step process designed to:
- Register plan holders (funeral plan clients)
- Link existing user accounts to plan holder profiles
- Create new user accounts with plan holder status
- Track personal, address, and emergency contact information
- Set up initial payment requirements for plan activation

The system supports **two registration modes**:
1. **Existing User Mode** - Link an already-registered user to a plan holder profile
2. **New User Mode** - Create a brand new user account and simultaneously register them as a plan holder

---

## 2. REGISTRATION FLOW DIAGRAM

```
START
  │
  ├─→ User Access System (signin/signup/client portal)
  │
  ├─→ Role Check: Plan Holder (Role ID = 4)?
  │   │
  │   ├─ NO → Redirect to /signin
  │   │
  │   └─ YES → Continue
  │
  ├─→ Registration Mode Selection
  │   │
  │   ├─ EXISTING USER MODE
  │   │   ├─ Select existing user from dropdown
  │   │   ├─ Validate user hasn't been linked before
  │   │   ├─ Validate user has role_id = 4 (Plan Holder)
  │   │   └─ Auto-fill user details (name, email, contact)
  │   │
  │   └─ NEW USER MODE
  │       ├─ Enter username (must be unique)
  │       ├─ Enter email (must be unique)
  │       ├─ Enter password (min 8 chars)
  │       ├─ Enter personal details
  │       └─ Select account status (pending/verified)
  │
  ├─→ Collect Plan Holder Information
  │   ├─ Branch assignment (required)
  │   ├─ Unique identifier (plan number)
  │   ├─ Personal info (DOB, gender, civil status, etc.)
  │   ├─ Address details (street, barangay, city)
  │   ├─ Emergency contact information
  │   └─ Spouse information (if applicable)
  │
  ├─→ Validation
  │   ├─ Check all required fields
  │   ├─ Validate email format
  │   ├─ Check username uniqueness
  │   ├─ Check email uniqueness
  │   ├─ Validate unique_identifier uniqueness
  │   └─ Verify password strength/match
  │
  ├─→ Database Transaction Start
  │   │
  │   ├─ IF EXISTING USER:
  │   │   └─ Update user: set is_plan_holder=1, branch_id
  │   │
  │   └─ IF NEW USER:
  │       └─ Insert into users table with:
  │           ├─ username, email, password_hash
  │           ├─ first_name, last_name, contact_number
  │           ├─ role_id = 4 (Plan Holder)
  │           ├─ branch_id
  │           ├─ account_status
  │           └─ is_plan_holder = 1
  │
  ├─→ Insert Plan Holder Record
  │   └─ plan_holders table:
  │       ├─ user_id (link to user)
  │       ├─ branch_id
  │       ├─ unique_identifier (plan number)
  │       ├─ Personal details
  │       ├─ Address details
  │       ├─ Emergency contact info
  │       └─ status = 'active' or 'inactive'
  │
  ├─→ Commit Transaction
  │
  ├─→ Create Notification
  │   └─ "Your plan holder profile was created successfully"
  │
  ├─→ Log Activity
  │   └─ ActivityLogService records registration event
  │
  └─→ SUCCESS → Redirect to dashboard with success message

    ON ERROR → Rollback transaction, show error message
```

---

## 3. KEY COMPONENTS

### 3.1 Controllers

#### A. PlanHolders.php (Admin/Branch Admin Entry Point)
**Namespace**: `App\Controllers\PlanHolders`  
**Access**: Admin (role_id=1), BranchAdmin (role_id=2)  

**Key Methods**:

1. **`register()`** - Display registration interface
   - Shows two tabs: Registration and Approvals
   - Lists existing users without plan holder profiles
   - Lists pending plan holder registrations (if approval workflow enabled)
   - Returns view with:
     - Available branches
     - Unlinked existing users
     - Pending registration approvals (admin only)

2. **`store()`** - Process registration form submission
   - Validates input based on registration mode
   - Creates new user (if new mode) or links existing user
   - Inserts plan holder record with all details
   - Returns to form with success/error message

3. **`registrationForm()`** - Client-facing registration form
   - For plan holders to self-register
   - Checks existing profile status
   - Shows branches available for registration

4. **`submitRegistration()`** - Process client registration submission
   - Validates plan holder can register
   - Saves registration to pending queue
   - Notifies admin for approval

#### B. ClientRegistrationController.php (Plan Holder Portal)
**Namespace**: `App\Controllers\Client\ClientRegistrationController`  
**Access**: Authenticated users with role_id=4 (Plan Holder)  

**Key Methods**:

1. **`planInfo()`** - Display plan information before registration
   - Checks user's access state (unregistered/awaiting_activation/active)
   - Redirects to next step if already registered
   - Shows plan details and benefits

2. **`planRegistration(int $planId)`** - Display registration form for plan holder
   - Retrieves program information
   - Shows available branches
   - Pre-fills user details if available
   - Displays form for entering/confirming information

---

### 3.2 Models

#### A. UserModel.php
**Table**: `users`  
**Primary Key**: `user_id`  

**Key Fields**:
```
user_id (int, primary key)
username (varchar, unique)
email (varchar, unique)
first_name (varchar)
last_name (varchar)
contact_number (varchar)
password_hash (varchar)
role_id (int) → 4 for plan holder
branch_id (int)
status (varchar)
account_status (varchar)
is_plan_holder (tinyint) → 1 if linked to plan holder
must_change_password (tinyint)
created_at (datetime)
updated_at (datetime)
```

#### B. PlanHolderModel.php
**Table**: `plan_holders`  
**Primary Key**: `plan_holder_id`  
**Foreign Key**: `user_id` → users table  

**Key Fields**:
```
plan_holder_id (int, primary key)
user_id (int, foreign key)
branch_id (int, foreign key)

PERSONAL INFORMATION:
├─ id_control_no (varchar)
├─ coordinator (varchar)
├─ application_date (date)
├─ date_of_birth (date)
├─ place_of_birth (varchar)
├─ age (int)
├─ gender (varchar)
├─ civil_status (varchar)
├─ citizenship (varchar)
├─ height (decimal)
├─ weight (decimal)

ADDRESS INFORMATION:
├─ address_no (varchar)
├─ address_street (varchar)
├─ address_barangay (varchar)
├─ address_city (varchar)

SPOUSE INFORMATION:
├─ spouse_name (varchar)
├─ spouse_birthdate (date)
├─ spouse_occupation (varchar)

ADDITIONAL:
├─ senior_citizen_id (varchar)
├─ organization_affiliation (varchar)
├─ emergency_contact_name (varchar)
├─ emergency_contact_number (varchar)
├─ emergency_contact_address (varchar)
├─ unique_identifier (varchar, unique) → Plan number
├─ status (varchar) → active/inactive
├─ is_linked_account (tinyint) → 1 if existing user linked
├─ created_at (datetime)
├─ updated_at (datetime)
```

---

## 4. REGISTRATION MODE DETAILS

### 4.1 EXISTING USER MODE

**When to Use**: Linking an already-registered user (from web, app, or previous system) to plan holder status

**Input Required**:
```
registration_mode = 'existing'
branch_id = [selected branch]
user_id = [selected existing user]
unique_identifier = [plan number/ID]
[All personal & address fields from form]
[All emergency contact fields]
[Spouse information if applicable]
```

**Validation Rules**:
- `branch_id`: Required, must be positive integer
- `user_id`: Required, must exist in users table with role_id=4
- `unique_identifier`: Required, must be unique in plan_holders table
- All address and personal fields: Standard text validation

**Process**:
1. Locate existing user by user_id
2. Verify user has role_id = 4 (Plan Holder role)
3. Verify user is NOT already linked to a plan holder (no existing plan_holder record)
4. Update users table:
   ```php
   UPDATE users SET 
       is_plan_holder = 1,
       branch_id = [selected_branch]
   WHERE user_id = [user_id]
   ```
5. Insert into plan_holders:
   ```php
   INSERT INTO plan_holders (
       user_id, branch_id, unique_identifier, 
       address_no, address_street, ..., status, is_linked_account
   ) VALUES (...)
   is_linked_account = 1  // Flag indicates existing account was linked
   ```

**Transaction Safety**: If either INSERT or UPDATE fails, entire transaction rolls back

**Example**:
```
User: John Doe (already exists as user_id=25)
Action: Existing User Mode Registration
Result:
  - users.is_plan_holder = 1
  - users.branch_id = 1 (Makati Branch)
  - plan_holders record created with user_id=25
  - is_linked_account = 1
```

---

### 4.2 NEW USER MODE

**When to Use**: Creating a brand new plan holder with no prior user account

**Input Required**:
```
registration_mode = 'new'
username = [new, unique username]
email = [new, unique email]
password = [min 8 characters]
password_confirm = [must match password]
first_name = [required]
last_name = [required]
contact_number = [optional]
branch_id = [selected branch]
account_status = ['pending' or 'verified']
unique_identifier = [plan number]
[All personal & address fields]
[All emergency contact fields]
```

**Validation Rules**:
- `username`: Required, 4-50 chars, unique in users table
- `email`: Required, valid email format, unique in users table
- `first_name`: Required, max 50 chars
- `last_name`: Required, max 50 chars
- `password`: Required, min 8 chars
- `password_confirm`: Required, must match password
- `branch_id`: Required, valid branch
- `account_status`: Required, 'pending' or 'verified'
- All other fields: Standard text/date validation

**Process**:
1. Validate all inputs
2. Start database transaction
3. Insert into users table:
   ```php
   INSERT INTO users (
       username, email, first_name, last_name, contact_number,
       password_hash, role_id, branch_id, status, account_status,
       is_plan_holder, must_change_password
   ) VALUES (
       $username, $email, $first_name, $last_name, $contact_number,
       password_hash($password, PASSWORD_DEFAULT),
       4,  // role_id for plan holder
       $branch_id,
       'active',  // user status
       $account_status,  // account verification status
       1,  // is_plan_holder = true
       0   // don't require password change
   )
   ```
4. Get returned user_id from INSERT
5. Insert into plan_holders:
   ```php
   INSERT INTO plan_holders (
       user_id, branch_id, unique_identifier, 
       address_no, address_street, ..., status, is_linked_account
   ) VALUES (
       $user_id, $branch_id, $unique_identifier, ...,
       'active',  // plan holder status
       0  // is_linked_account = false (new account)
   )
   ```
6. Commit transaction
7. Send welcome notification to new user
8. Log activity

**Password Hashing**: Uses PHP's `password_hash()` with default algorithm (PASSWORD_DEFAULT = bcrypt)

**Transaction Safety**: If user insert fails, plan holder record is never created

**Example**:
```
Action: New User Mode Registration
Data:
  username: "maria.santos"
  email: "maria@example.com"
  password: "SecureP@ss123"
  first_name: "Maria"
  last_name: "Santos"
  branch_id: 1
  account_status: "verified"
Result:
  - New user created with user_id=18
  - New plan_holder record created with plan_holder_id=10
  - user.is_plan_holder = 1
  - plan_holder.user_id = 18
```

---

## 5. REGISTRATION FORM FIELDS

### 5.1 Account Selection Section
```
Registration Mode (Radio Buttons):
  ☐ Client already has an account (Existing User Mode)
  ☐ Client does not have an account yet (New User Mode)

Branch Selection (Dropdown):
  └─ Required, dynamically populated from branches table
```

### 5.2 Conditional Fields Based on Mode

#### IF EXISTING USER MODE:
```
Existing User Email (Text Input):
  - Used to search/lookup existing user
  - Auto-fills the following readonly fields:
    ├─ Username (readonly)
    ├─ First Name (readonly)
    ├─ Last Name (readonly)
    └─ Contact Number (readonly)

Hidden user_id Input:
  └─ Populated by JavaScript when user is selected
```

#### IF NEW USER MODE:
```
Username (Text Input):
  - Min 4, Max 50 chars
  - Must be unique
  - Regex: alphanumeric + underscore only

Email (Email Input):
  - Must be valid email format
  - Must be unique

Password (Password Input):
  - Min 8 characters
  - Should include mix of: uppercase, lowercase, numbers, symbols
  
Password Confirmation (Password Input):
  - Must match password field exactly

First Name (Text Input):
  - Required
  - Max 50 chars

Last Name (Text Input):
  - Required
  - Max 50 chars

Contact Number (Tel Input):
  - Optional
  - Typical format: +63 9XX XXX XXXX (Philippines)

Account Status (Radio Buttons):
  ☐ Pending (account needs email verification)
  ☐ Verified (account immediately active)
```

### 5.3 Common Plan Holder Information (Both Modes)

#### Personal Information Section:
```
Unique Identifier/Plan Number (Text, Required, Unique):
  └─ System-generated or manual ID

Date of Birth (Date Input):
  └─ YYYY-MM-DD format

Place of Birth (Text Input):
  └─ City/Municipality name

Age (Number Input):
  └─ Auto-calculated or manual entry

Gender (Select Dropdown):
  ├─ Male
  ├─ Female
  └─ Prefer not to say

Civil Status (Select Dropdown):
  ├─ Single
  ├─ Married
  ├─ Separated
  ├─ Divorced
  └─ Widowed

Citizenship (Text Input):
  └─ Default: Filipino

Height (Decimal Number):
  └─ In centimeters (e.g., 165.5)

Weight (Decimal Number):
  └─ In kilograms (e.g., 65.0)
```

#### Address Information Section:
```
House/Block No. (Text):
  └─ Example: "123", "Block A, Lot 5"

Street (Text, Required):
  └─ Example: "Makati Avenue"

Barangay (Text, Required):
  └─ Barangay name

City/Municipality (Text, Required):
  └─ Example: "Makati", "Quezon City"
```

#### Spouse Information Section:
```
Spouse Name (Text):
  └─ Only if married

Spouse Birthdate (Date):
  └─ YYYY-MM-DD format

Spouse Occupation (Text):
  └─ Current job title/type
```

#### Emergency Contact Section:
```
Emergency Contact Name (Text, Required):
  └─ Full name of emergency contact

Emergency Contact Number (Tel, Required):
  └─ Mobile or landline number

Emergency Contact Address (Text, Required):
  └─ Full address of emergency contact
```

#### Additional Information Section:
```
Senior Citizen ID (Text):
  └─ If applicable

Organization Affiliation (Text):
  └─ Religious group, professional org, etc.

Coordinator (Text):
  └─ Name of referring coordinator/agent

ID Control Number (Text):
  └─ Internal identification number
```

---

## 6. DATA VALIDATION & ERROR HANDLING

### 6.1 Validation Rules (in order of execution)

```php
// BASE RULES (both modes):
'branch_id' => 'required|is_natural_no_zero'
'unique_identifier' => 'required|max_length[100]|is_unique[plan_holders.unique_identifier]'
'status' => 'required|in_list[active,inactive]'

// EXISTING USER MODE:
'user_id' => 'required|is_natural_no_zero'

// NEW USER MODE:
'username' => 'required|min_length[4]|max_length[50]|is_unique[users.username]'
'email' => 'required|valid_email|max_length[100]|is_unique[users.email]'
'first_name' => 'required|max_length[50]'
'last_name' => 'required|max_length[50]'
'password' => 'required|min_length[8]'
'password_confirm' => 'required|matches[password]'
'account_status' => 'required|in_list[pending,verified]'
```

### 6.2 Custom Validations (in code)

**Existing User Mode**:
```php
// Check user exists and has role_id = 4
if (!$user || (int)$user['role_id'] !== 4) {
    throw new RuntimeException('Selected user is invalid for plan holder linking');
}

// Check user not already linked to a plan holder
if ($alreadyLinked = $planHolderModel->where('user_id', $userId)->first()) {
    throw new RuntimeException('Selected user is already linked to a plan holder profile');
}
```

**New User Mode**:
```php
// Username/email uniqueness checked via validation rules
// Password strength checked via minimum length rule
// Password match checked via 'matches' rule
```

### 6.3 Error Messages

```
On validation failure:
  - Join all validation error messages
  - Redirect back with withInput() to preserve form data
  - Display in alert: "error message"

On existing user not found:
  - "Selected user is invalid for plan holder linking."

On existing user already linked:
  - "Selected user is already linked to a plan holder profile."

On database transaction failure:
  - "Database transaction failed."
  - Rolls back all changes

On user insert failure:
  - "Unable to create the new user account."

On plan holder insert failure:
  - "Unable to save plan holder record."

Generic:
  - "Plan holder registration completed successfully."
  - "Error: [specific exception message]"
```

---

## 7. DATABASE TRANSACTION FLOW

```sql
BEGIN TRANSACTION

-- EXISTING USER MODE:
UPDATE users 
SET is_plan_holder = 1, 
    branch_id = ?
WHERE user_id = ?

INSERT INTO plan_holders (
    user_id, branch_id, unique_identifier, 
    address_no, address_street, address_barangay, address_city,
    date_of_birth, place_of_birth, age, gender, civil_status,
    citizenship, height, weight, spouse_name, spouse_birthdate,
    spouse_occupation, senior_citizen_id, organization_affiliation,
    emergency_contact_name, emergency_contact_number, emergency_contact_address,
    status, is_linked_account
) VALUES (?, ?, ?, ...)

-- OR NEW USER MODE:
INSERT INTO users (
    username, email, first_name, last_name, contact_number,
    password_hash, role_id, branch_id, status, account_status,
    is_plan_holder, must_change_password
) VALUES (?, ?, ?, ?, ?, ?, 4, ?, 'active', ?, 1, 0)

SET @userId = LAST_INSERT_ID()

INSERT INTO plan_holders (...)
VALUES (@userId, ...)

-- Commit if all successful
COMMIT

-- Rollback if any error occurs
ROLLBACK
```

---

## 8. POST-REGISTRATION FLOW

### 8.1 Notifications
```php
(new NotificationService())->notify(
    $userId,
    'Your plan holder profile was created or linked successfully.',
    'registration_pending'
);
```
- Creates notification record in `notifications` table
- Notifies user of successful registration
- Type: `registration_pending`

### 8.2 Activity Logging
```php
(new ActivityLogService())->log(
    session('user_id'),  // Who performed action
    'created',            // Action type
    'plan_holder',        // Entity type
    $planHolderId,        // Entity ID
    'Created or linked plan holder profile'  // Description
);
```
- Records in `activity_logs` table
- Tracks who registered the plan holder
- Timestamp and IP address captured

### 8.3 Redirect & Response
```php
return redirect()->back()
    ->with('success', 'Plan holder registration completed successfully.');
```
- Redirects to previous page (registration form)
- Flash message displayed once
- Form is now empty and ready for next registration

---

## 9. PLAN HOLDER STATUSES

### Status Field Values:

| Status | Meaning | Used For |
|--------|---------|----------|
| `active` | Plan holder is active and can register for plans | Normal operations |
| `inactive` | Plan holder is inactive, cannot use services | Suspended accounts, administrative holds |

### Account Status (User Table):

| Status | Meaning |
|--------|---------|
| `pending` | Account created but not verified (may need email confirmation) |
| `verified` | Account is verified and active |

### Role ID:
| Role ID | Role Name |
|---------|-----------|
| 1 | Admin |
| 2 | Branch Admin |
| 3 | Staff |
| 4 | Plan Holder (Client) |

---

## 10. ACCESS CONTROL

### Who Can Register?

**Admin & Branch Admin**:
- Can register plan holders via staff portal
- Can link existing users to plan holder profiles
- Can create new user accounts with plan holder status
- Access to: `PlanHolders::register()`, `PlanHolders::store()`

**Plan Holders (Users)**:
- Can self-register via client portal (conditional)
- Can update their own profile information
- Can view their registration status
- Access to: `ClientRegistrationController::planRegistration()`, `PlanHolders::registrationForm()`

**Unauthenticated Users**:
- Can access public signup flow
- Auto-assigned role_id = 4 (Plan Holder) during signup
- Must complete registration to become active

---

## 11. NEXT STEPS AFTER REGISTRATION

### 1. **Initial Payment Submission**
   - Plan holder redirected to initial payment form
   - Must submit first monthly payment
   - Routes to: `/initial-payment` or `ClientPaymentInitialController::initialPayment()`

### 2. **Payment Approval (Auto or Manual)**
   - Admin/BranchAdmin reviews initial payment
   - If approved via cash: `status = 'verified'` → Auto-activated
   - If submitted via GCash: `status = 'awaiting_verification'` → Pending review

### 3. **Plan Activation**
   - After successful payment:
     - `plan_holders.status = 'active'`
     - `users.account_status = 'verified'` (if pending)
     - `plans.membership_state = 'active'`
     - `plans.next_due_date` calculated

### 4. **Membership Access**
   - Plan holder can now:
     - View membership dashboard
     - Submit service requests
     - View payment history
     - Update profile information
     - Track membership status

---

## 12. RELATED ENTITIES & DEPENDENCIES

### Entity Relationships:
```
User (user_id)
  ├─ 1 role (role_id) → roles table
  ├─ 1 branch (branch_id) → branches table
  └─ 0..* plan_holders (user_id)

Plan Holder (plan_holder_id)
  ├─ 1 user (user_id) → users table
  ├─ 1 branch (branch_id) → branches table
  ├─ 0..* plans (plan_holder_id)
  ├─ 0..* notifications (user_id)
  └─ 0..* service applications

Plan (plan_id)
  ├─ 1 plan holder (plan_holder_id)
  ├─ 1 package (package_id)
  ├─ 0..* payments (plan_id)
  └─ 0..* beneficiaries (plan_id)
```

### Key Services Used:
- `MembershipService` - Membership tracking & calculations
- `NotificationService` - Send notifications to users
- `ActivityLogService` - Log all actions for audit trail
- `ApprovalService` - Handle payment approval workflows
- `ValidationRules` - Centralized validation rules

---

## 13. COMMON SCENARIOS

### Scenario 1: Existing Offline Customer
```
Story: Maria registered in-store 3 months ago but never logged in online
Solution: Use EXISTING USER MODE
Steps:
  1. Search Maria's email in dropdown
  2. Select her account
  3. Auto-fills: username, name, contact
  4. Fill additional plan holder details
  5. Set status = 'active'
  6. Submit → Maria's account is now linked to plan holder profile
Result: Maria can log in and complete initial payment
```

### Scenario 2: Brand New Customer
```
Story: John is a new customer walking into the branch with no prior account
Solution: Use NEW USER MODE
Steps:
  1. Create account: username=john.doe, email=john@example.com
  2. Set password
  3. Enter personal details (DOB, gender, etc.)
  4. Enter address details
  5. Enter emergency contact
  6. Set account_status = 'verified' (admin verified identity)
  7. Submit → Account and plan holder profile created together
Result: John receives welcome email and can proceed to initial payment
```

### Scenario 3: Duplicate Account Prevention
```
Scenario: Someone tries to register with existing email
Error: "Email already exists"
Prevention: is_unique[users.email] validation rule
```

### Scenario 4: Account Already Linked
```
Scenario: Someone tries to link user that already has plan holder profile
Error: "Selected user is already linked to a plan holder profile"
Prevention: Custom validation check finds existing plan_holder record
```

---

## 14. TROUBLESHOOTING

### Issue: "Invalid registration mode selected"
**Cause**: Form submitted with mode not in ['existing', 'new']  
**Solution**: Ensure radio button value is 'existing' or 'new'

### Issue: "Database transaction failed"
**Cause**: Any INSERT/UPDATE operation failed  
**Solution**: Check database connectivity, permissions, storage space

### Issue: "Unable to create the new user account"
**Cause**: User insert failed (duplicate username, email, etc.)  
**Solution**: Verify username and email are unique

### Issue: "Unable to save plan holder record"
**Cause**: Plan holder insert failed  
**Solution**: Verify all required fields are provided, check unique_identifier uniqueness

### Issue: Plan holder appears as inactive
**Cause**: Admin set status='inactive' during registration  
**Solution**: Plan holder must request admin to change status='active'

### Issue: User cannot log in after registration
**Cause**: account_status='pending' (unverified account)  
**Solution**: Change account_status to 'verified', or email verification system must be enabled

---

## 15. SECURITY CONSIDERATIONS

### Passwords:
- Hashed using PHP's `password_hash()` with bcrypt (PASSWORD_DEFAULT)
- Never stored in plaintext
- Never transmitted in logs or emails

### Transaction Safety:
- All changes wrapped in database transaction
- Automatic rollback on any error
- Ensures data consistency

### Validation:
- All inputs validated server-side
- Client-side validation for UX only
- No trust of user-submitted data

### Authorization:
- Role checks before allowing registration
- Admin/Branch Admin only for staff portal
- Plan holders only for self-registration

### Audit Trail:
- All registrations logged in activity_logs
- Who registered, when, and what was changed
- Enables investigation of issues

---

## 16. DATABASE SCHEMA SUMMARY

### users table
```
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    contact_number VARCHAR(20),
    password_hash VARCHAR(255),
    role_id INT FOREIGN KEY → roles.role_id,
    branch_id INT FOREIGN KEY → branches.branch_id,
    status VARCHAR(20),
    account_status VARCHAR(20),
    is_plan_holder TINYINT DEFAULT 0,
    must_change_password TINYINT DEFAULT 0,
    created_at DATETIME,
    updated_at DATETIME
);
```

### plan_holders table
```
CREATE TABLE plan_holders (
    plan_holder_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE FOREIGN KEY → users.user_id,
    branch_id INT FOREIGN KEY → branches.branch_id,
    unique_identifier VARCHAR(100) UNIQUE,
    date_of_birth DATE,
    place_of_birth VARCHAR(100),
    age INT,
    gender VARCHAR(20),
    civil_status VARCHAR(20),
    citizenship VARCHAR(50),
    height DECIMAL(5,2),
    weight DECIMAL(5,2),
    address_no VARCHAR(100),
    address_street VARCHAR(255),
    address_barangay VARCHAR(100),
    address_city VARCHAR(100),
    spouse_name VARCHAR(100),
    spouse_birthdate DATE,
    spouse_occupation VARCHAR(100),
    emergency_contact_name VARCHAR(100),
    emergency_contact_number VARCHAR(20),
    emergency_contact_address TEXT,
    senior_citizen_id VARCHAR(50),
    organization_affiliation VARCHAR(100),
    status VARCHAR(20) DEFAULT 'active',
    is_linked_account TINYINT DEFAULT 0,
    created_at DATETIME,
    updated_at DATETIME
);
```

---

**End of Documentation**

This comprehensive guide covers the entire client registration logic, from initial access through post-registration workflows.
