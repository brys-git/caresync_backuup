# UI/UX Consistency & Standardization Guide

## Overview

This guide establishes standardized UI/UX patterns for the KAAGAPAY Web-Based Management System. All new views and components should follow these guidelines for consistency, maintainability, and user experience.

---

## 1. Color Scheme & Status Indicators

### Status Colors (Bootstrap 5 Context Colors)

| Status | Color | Class | Usage |
|--------|-------|-------|-------|
| **Active** | Green (#198754) | `text-bg-success` | Active memberships, approved registrations, completed actions |
| **Pending/In Progress** | Orange (#FFC107) | `text-bg-warning` | Under review, delinquent, processing payments, in-progress services |
| **Error/Inactive** | Red (#DC3545) | `text-bg-danger` | Rejected, cancelled, suspended memberships, critical errors |
| **Informational** | Blue (#0D6EFD) | `text-bg-info` | New registrations, general information, help messages |
| **Secondary** | Gray (#6C757D) | `text-bg-secondary` | Neutral states, default status |

### Status Mapping

```php
// In controllers, map states to status colors:
$stateColors = [
    // Positive states → success (green)
    'active' => 'success',
    'approved' => 'success',
    'completed' => 'success',
    'paid' => 'success',
    'verified' => 'success',
    
    // Warning states → warning (orange)
    'pending' => 'warning',
    'delinquent' => 'warning',
    'processing' => 'warning',
    
    // Error states → danger (red)
    'rejected' => 'danger',
    'cancelled' => 'danger',
    'suspended' => 'danger',
    'failed' => 'danger',
    
    // Info states → info (blue)
    'new' => 'info',
    'draft' => 'info',
];
```

---

## 2. Reusable Components

All components are located in `app/Views/components/` and use standardized naming conventions.

### 2.1 Status Badge Component

**File:** `components/status_badge.php`  
**Purpose:** Display status with standardized colors and labels

**Usage:**
```blade
<?= view('components/status_badge', ['status' => 'active']) ?>
<?= view('components/status_badge', ['status' => 'pending', 'label' => 'Under Review']) ?>
<?= view('components/status_badge', ['status' => 'delinquent', 'class' => 'ms-2']) ?>
```

**Features:**
- Automatic color mapping based on status
- Optional custom label
- Additional CSS classes support
- Escaped output for security

---

### 2.2 Info Card Component

**File:** `components/info_card.php`  
**Purpose:** Display key-value information in a consistent card format

**Usage:**
```blade
<?= view('components/info_card', [
    'label' => 'Membership Status',
    'value' => 'Active',
    'size' => 'md-3',
    'bg' => 'light'
]) ?>
```

**Features:**
- Standardized border and padding
- Grid-responsive sizing (md-3, md-4, md-6, etc.)
- Optional background colors
- Clean, minimal design

---

### 2.3 Alert Component

**File:** `components/alert.php`  
**Purpose:** Display contextual alerts (success, info, warning, danger)

**Usage:**
```blade
<?= view('components/alert', [
    'type' => 'success',
    'message' => 'Payment processed successfully'
]) ?>

<?= view('components/alert', [
    'type' => 'warning',
    'title' => 'Attention Required',
    'message' => 'Your membership is delinquent',
    'dismissible' => true
]) ?>
```

**Features:**
- Type validation (success, info, warning, danger)
- Optional title
- Dismissible option
- Bootstrap alert styling

---

### 2.4 Membership State Component

**File:** `components/membership_state.php`  
**Purpose:** Display membership status with contextual information

**Usage:**
```blade
<?= view('components/membership_state', [
    'state' => 'active',
    'compact' => false  // Detailed display with icon and description
]) ?>

<?= view('components/membership_state', [
    'state' => 'delinquent',
    'overdueMonths' => 3,
    'compact' => true  // Badge-only display
]) ?>
```

**Features:**
- Predefined state icons and descriptions
- Compact and detailed display modes
- Automatic overdue month messaging
- Contextual color coding

---

### 2.5 Form Field Component

**File:** `components/form_field.php`  
**Purpose:** Render form fields with labels, validation, and help text

**Usage:**
```blade
<?= view('components/form_field', [
    'name' => 'email',
    'label' => 'Email Address',
    'type' => 'email',
    'value' => old('email'),
    'placeholder' => 'user@example.com',
    'required' => true,
    'help' => 'We\'ll never share your email',
    'errors' => $errors
]) ?>

<?= view('components/form_field', [
    'name' => 'status',
    'label' => 'Status',
    'type' => 'select',
    'options' => ['active' => 'Active', 'inactive' => 'Inactive'],
    'value' => old('status'),
    'required' => true
]) ?>
```

**Features:**
- Support for text, email, password, textarea, select types
- Automatic error display and styling
- Help text support
- Required field indicators
- Error state CSS classes

---

### 2.6 Table Row Component

**File:** `components/table_row.php`  
**Purpose:** Render table rows with consistent styling

**Usage:**
```blade
<table class="table">
    <tbody>
        <?= view('components/table_row', [
            'label' => 'Status',
            'value' => 'Active'
        ]) ?>
        
        <?= view('components/table_row', [
            'label' => 'Membership',
            'badges' => [
                ['status' => 'active', 'label' => 'Active']
            ]
        ]) ?>
    </tbody>
</table>
```

**Features:**
- Standardized layout
- Support for text or badge values
- Multiple badges support

---

### 2.7 Data Display Card Component

**File:** `components/data_card.php`  
**Purpose:** Display structured data with optional action buttons

**Usage:**
```blade
<?= view('components/data_card', [
    'title' => 'Membership Information',
    'data' => [
        ['label' => 'Plan Name', 'value' => 'Damayan Plan', 'size' => 'md-6'],
        ['label' => 'Status', 'value' => '', 'badges' => [['status' => 'active']], 'size' => 'md-6'],
        ['label' => 'Monthly Fee', 'value' => 'PHP 240.00', 'size' => 'md-3'],
        ['label' => 'Coverage Until', 'value' => 'June 2026', 'size' => 'md-3'],
    ],
    'actions' => [
        ['label' => 'Edit', 'url' => '/edit', 'class' => 'btn-outline-primary'],
        ['label' => 'View Details', 'url' => '/details', 'class' => 'btn-outline-secondary'],
    ]
]) ?>
```

**Features:**
- Title with optional actions
- Flexible grid layout
- Badge support for status fields
- Action button group

---

### 2.8 Button Component

**File:** `components/button.php`  
**Purpose:** Render standardized buttons

**Usage:**
```blade
<?= view('components/button', [
    'label' => 'Submit',
    'url' => '/submit',
    'type' => 'primary'
]) ?>

<?= view('components/button', [
    'label' => 'Cancel',
    'url' => '/back',
    'type' => 'secondary',
    'outline' => true,
    'size' => 'sm'
]) ?>

<?= view('components/button', [
    'label' => 'Delete',
    'url' => '/delete',
    'type' => 'danger',
    'outline' => true,
    'icon' => '🗑'
]) ?>
```

**Features:**
- Multiple button types
- Outline variant
- Size options (sm, md, lg)
- Icon support
- Full-width option
- Custom target support

---

## 3. Best Practices

### 3.1 Status Display

✅ **DO:**
- Use consistent color mapping for all statuses
- Display status with `status_badge` component
- Show contextual descriptions for important states
- Use badges for quick visual recognition

❌ **DON'T:**
- Use random colors for statuses
- Mix text and badge representations
- Abbreviate status names (use full names)
- Use color alone without text labels

### 3.2 Information Display

✅ **DO:**
- Use `info_card` for key-value display
- Use `data_card` for grouped information
- Organize info logically (related items together)
- Use meaningful labels

❌ **DON'T:**
- Hardcode card styling inline
- Mix different card styles on same page
- Use unclear abbreviations
- Overcrowd cards with too much info

### 3.3 Form Design

✅ **DO:**
- Use `form_field` component for all fields
- Show validation errors inline
- Mark required fields clearly
- Provide helpful hints/descriptions

❌ **DON'T:**
- Mix form styling approaches
- Display errors separately from fields
- Use generic field labels
- Forget to validate server-side

### 3.4 Alerts & Messaging

✅ **DO:**
- Use appropriate alert types (success, warning, danger, info)
- Make alerts dismissible when appropriate
- Include clear, actionable messages
- Use alert component for consistency

❌ **DON'T:**
- Use hardcoded alert classes
- Mix alert styling
- Use vague error messages
- Show too many alerts at once

### 3.5 Responsive Design

✅ **DO:**
- Use Bootstrap grid classes (col-md-*, col-lg-*, etc.)
- Test on mobile and tablet
- Use responsive utilities (d-block, d-md-inline, etc.)
- Stack components vertically on small screens

❌ **DON'T:**
- Use fixed widths
- Forget to include mobile breakpoints
- Hide important content on mobile
- Use horizontal scrolling

---

## 4. Migration Guide

### Converting Existing Views

To update existing views to use the new components:

**Before:**
```blade
<div class="card">
    <div class="card-body">
        <h5 class="mb-3">Account Summary</h5>
        <div class="row g-3">
            <div class="col-md-3">
                <div class="border rounded p-3">
                    <small class="text-muted d-block">Status</small>
                    <strong><span class="badge text-bg-<?= $status === 'active' ? 'success' : 'warning' ?>"><?= ucfirst($status) ?></span></strong>
                </div>
            </div>
        </div>
    </div>
</div>
```

**After:**
```blade
<?= view('components/data_card', [
    'title' => 'Account Summary',
    'data' => [
        ['label' => 'Status', 'value' => '', 'badges' => [['status' => $status]], 'size' => 'md-3'],
    ]
]) ?>
```

---

## 5. Component Library Reference

| Component | File | Purpose | Key Features |
|-----------|------|---------|---|
| Status Badge | `status_badge.php` | Display status with color | Auto color mapping, custom labels |
| Info Card | `info_card.php` | Key-value display | Grid sizing, backgrounds |
| Alert | `alert.php` | Contextual messages | Types, dismissible, titles |
| Membership State | `membership_state.php` | Membership status | Icons, descriptions, compact mode |
| Form Field | `form_field.php` | Form inputs | Multiple types, validation, help text |
| Table Row | `table_row.php` | Table data | Text or badge values |
| Data Card | `data_card.php` | Grouped data | Structured layout, actions |
| Button | `button.php` | Standardized buttons | Multiple types, sizes, icons |

---

## 6. Typography & Spacing

### Font Sizes
- **Headings**: Use `h1`-`h6` Bootstrap classes
- **Body Text**: Default Bootstrap sizing
- **Small Text**: Use `<small>` or `.small` class for labels/hints
- **Muted Text**: Use `.text-muted` for secondary information

### Spacing
- **Cards**: 3-4 items per row on desktop, 1 on mobile
- **Fields**: Vertical spacing with `mb-3` (Bootstrap standard)
- **Gaps**: Use Bootstrap gap utilities (`g-3`, `g-2`, etc.)
- **Padding**: Component defaults (3rem internal padding)

---

## 7. Accessibility

✅ **DO:**
- Use semantic HTML (headings, labels, buttons)
- Include `aria-label` for icons
- Use proper color contrast (WCAG AA minimum)
- Test with keyboard navigation

❌ **DON'T:**
- Rely on color alone for information
- Use divs/spans for interactive elements
- Skip form labels
- Forget alt text for images

---

## 8. Browser Compatibility

All components use Bootstrap 5, supporting:
- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- Mobile browsers (iOS Safari, Chrome Mobile)

---

## 9. Performance Considerations

- Components are reusable and cacheable
- No external dependencies beyond Bootstrap
- Minimal additional CSS/JavaScript
- Optimized for server-side rendering

---

## 10. Implementation Checklist

Before deploying UI/UX updates:

- [ ] All new components use standardized status colors
- [ ] Forms use `form_field` component
- [ ] Info displays use `info_card` or `data_card`
- [ ] Alerts use `alert` component
- [ ] Status badges use `status_badge` component
- [ ] No hardcoded inline styles
- [ ] All components properly escaped for security
- [ ] Responsive design tested (mobile, tablet, desktop)
- [ ] Accessibility tested (keyboard nav, screen readers)
- [ ] Documentation updated with new components
- [ ] QA testing completed

---

## 11. Future Enhancements

Planned for Phase 3+ Continuation:
- Dark mode support
- Animation/transition effects
- Advanced form validations
- Custom theme colors
- Print-friendly layouts
- Accessibility improvements

---

**Created:** May 8, 2026  
**Version:** 1.0  
**Status:** Active  
**Maintainer:** Development Team
