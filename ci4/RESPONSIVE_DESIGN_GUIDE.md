# Responsive Design & Mobile Optimization Guide

## Overview

This guide establishes responsive design standards for the KAAGAPAY Web-Based Management System. All views should follow Bootstrap 5 responsive patterns for optimal experience across all device sizes.

---

## 1. Breakpoints & Device Sizes

Bootstrap 5 uses these standard breakpoints:

| Breakpoint | Device | Width | CSS Class |
|-----------|--------|-------|-----------|
| **XS** (Extra Small) | Mobile Phones | < 576px | None (default) |
| **SM** (Small) | Large Phones, Tablets (portrait) | ≥ 576px | `sm` |
| **MD** (Medium) | Tablets (landscape) | ≥ 768px | `md` |
| **LG** (Large) | Small Desktops | ≥ 992px | `lg` |
| **XL** (Extra Large) | Desktops | ≥ 1200px | `xl` |
| **XXL** | Large Desktops | ≥ 1400px | `xxl` |

### Common Breakpoint Patterns

```blade
<!-- Mobile-first: stacks vertically, then changes at medium and large -->
<div class="col-12 col-md-6 col-lg-4">
    <!-- Full width on mobile, half on tablets, third on desktops -->
</div>

<!-- Hide on mobile, show on tablet and up -->
<div class="d-none d-md-block">
    <!-- Desktop navigation, detailed sidebar, etc. -->
</div>

<!-- Show on mobile, hide on tablet and up -->
<div class="d-md-none">
    <!-- Mobile hamburger menu, simplified view, etc. -->
</div>
```

---

## 2. Grid System

Bootstrap 5 uses a 12-column responsive grid system.

### 2.1 Basic Grid Usage

**DO:**
```blade
<!-- Mobile: stacks vertically (full width) -->
<!-- Tablet+: 2 columns -->
<!-- Desktop+: 3 columns -->
<div class="row g-3">
    <div class="col-12 col-md-6 col-lg-4">Item 1</div>
    <div class="col-12 col-md-6 col-lg-4">Item 2</div>
    <div class="col-12 col-md-6 col-lg-4">Item 3</div>
</div>
```

**DON'T:**
```blade
<!-- Fixed width - not responsive -->
<div style="width: 300px;">Item</div>

<!-- Unclear breakpoints -->
<div class="col-md-8">Item</div>
```

### 2.2 Common Grid Patterns

```blade
<!-- Sidebar Layout (mobile stacks, desktop 2-col) -->
<div class="row g-3">
    <div class="col-12 col-md-8">Main Content</div>
    <div class="col-12 col-md-4">Sidebar</div>
</div>

<!-- Two-column layout (mobile stacks, desktop splits equally) -->
<div class="row g-3">
    <div class="col-12 col-md-6">Left Column</div>
    <div class="col-12 col-md-6">Right Column</div>
</div>

<!-- Flexible wrapping (auto-wraps based on space) -->
<div class="row g-3">
    <div class="col-12 col-sm-6 col-md-4">Card 1</div>
    <div class="col-12 col-sm-6 col-md-4">Card 2</div>
    <div class="col-12 col-sm-6 col-md-4">Card 3</div>
</div>

<!-- Info Cards (flex: 1 per row on mobile, 2 on tablet, 3 on desktop) -->
<div class="row g-2">
    <div class="col-12 col-md-6 col-lg-4">
        <?= view('components/info_card', [...]) ?>
    </div>
    <!-- More cards... -->
</div>
```

---

## 3. Mobile-First Approach

Always start with mobile design and enhance for larger screens.

### 3.1 Pattern Examples

```blade
<!-- Mobile: single column, stacked -->
<!-- Tablet+: two columns -->
<!-- Desktop+: three columns -->
<div class="row g-2 g-md-3">
    <div class="col-12 col-md-6 col-lg-4">Content</div>
</div>

<!-- Mobile: hidden navigation -->
<!-- Tablet+: visible navigation -->
<nav class="d-none d-md-block">
    <!-- Full navigation menu -->
</nav>

<!-- Mobile: hamburger menu -->
<!-- Tablet+: hidden -->
<button class="btn d-md-none" data-bs-toggle="offcanvas">Menu</button>

<!-- Mobile: single input per row -->
<!-- Tablet+: side-by-side inputs -->
<form>
    <div class="row g-2">
        <div class="col-12 col-md-6">
            <?= view('components/form_field', [...]) ?>
        </div>
        <div class="col-12 col-md-6">
            <?= view('components/form_field', [...]) ?>
        </div>
    </div>
</form>
```

---

## 4. Responsive Display Utilities

Bootstrap provides display utilities for showing/hiding content.

### 4.1 Common Display Patterns

```blade
<!-- Hide on mobile (screens < 768px), show on tablet and up -->
<div class="d-none d-md-block">
    Desktop navigation, tables, detailed info
</div>

<!-- Show on mobile only -->
<div class="d-md-none">
    Mobile hamburger menu, simplified views
</div>

<!-- Show inline on mobile, block on tablet up -->
<div class="d-inline d-md-block">
    Content that changes layout
</div>

<!-- Flexbox: stacked on mobile, row on tablet up -->
<div class="d-flex flex-column flex-md-row gap-3">
    <div>Item 1</div>
    <div>Item 2</div>
</div>

<!-- Margins: different on mobile vs desktop -->
<div class="mb-2 mb-md-3 mb-lg-4">Responsive spacing</div>
```

### 4.2 Display Utility Reference

| Class | Mobile | Tablet+ | Desktop+ |
|-------|--------|---------|----------|
| `d-block` | Block | Block | Block |
| `d-md-block` | Hidden | Block | Block |
| `d-none d-md-block` | Hidden | Block | Block |
| `d-md-none` | Block | Hidden | Hidden |
| `d-flex` | Flex | Flex | Flex |
| `d-flex flex-column flex-md-row` | Column | Row | Row |

---

## 5. Typography Responsive Sizing

### 5.1 Heading Sizes

```blade
<!-- Responsive headings: larger on desktop, smaller on mobile -->
<h1 class="h2 h1-md h1-lg">Main Heading</h1>

<!-- Better approach: use heading classes with Bootstrap -->
<h1 class="h3 h2-md h1-lg">Adaptive Heading</h1>

<!-- Or use custom CSS for REM-based sizing -->
<h1 style="font-size: clamp(1.5rem, 5vw, 2.5rem);">Fluid Heading</h1>
```

### 5.2 Text Sizing

```blade
<!-- Default text remains readable on all sizes -->
<p>Normal paragraph text</p>

<!-- Smaller on mobile, larger on desktop -->
<p class="small text-md-normal">Secondary text</p>

<!-- Hide longer text on mobile, show on tablet -->
<p class="d-none d-md-block">Detailed description...</p>
```

---

## 6. Tables - Responsive Handling

### 6.1 Scrollable Table (Small Screens)

```blade
<!-- Wraps table in scrollable container on mobile -->
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Header 1</th>
                <th>Header 2</th>
                <th>Header 3</th>
            </tr>
        </thead>
        <tbody>
            <!-- Rows... -->
        </tbody>
    </table>
</div>
```

### 6.2 Card-Based Table (Alternative for Mobile)

```blade
<!-- Show as table on desktop, cards on mobile -->
<div class="d-none d-md-block">
    <!-- Desktop table view -->
    <div class="table-responsive">
        <table class="table"><!-- ... --></table>
    </div>
</div>

<div class="d-md-none">
    <!-- Mobile card view -->
    <div class="row g-3">
        <?php foreach ($items as $item): ?>
            <div class="col-12">
                <?= view('components/data_card', [
                    'title' => $item['name'],
                    'data' => [/* ... */]
                ]) ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
```

---

## 7. Forms - Responsive Layout

### 7.1 Single Column (Mobile) to Multi-Column (Desktop)

```blade
<form>
    <div class="row g-3">
        <!-- Field 1: full width on mobile, half on tablet, third on desktop -->
        <div class="col-12 col-md-6 col-lg-4">
            <?= view('components/form_field', [
                'name' => 'field1',
                'label' => 'Field 1',
                // ...
            ]) ?>
        </div>
        
        <!-- Field 2: same layout -->
        <div class="col-12 col-md-6 col-lg-4">
            <?= view('components/form_field', [...]) ?>
        </div>
        
        <!-- Full-width field -->
        <div class="col-12">
            <?= view('components/form_field', [...]) ?>
        </div>
        
        <!-- Buttons -->
        <div class="col-12 d-flex flex-column flex-md-row gap-2">
            <?= view('components/button', [
                'label' => 'Submit',
                'type' => 'primary',
                'block' => false
            ]) ?>
            
            <?= view('components/button', [
                'label' => 'Cancel',
                'type' => 'secondary',
                'outline' => true,
                'block' => false
            ]) ?>
        </div>
    </div>
</form>
```

---

## 8. Navigation Responsive Patterns

### 8.1 Desktop Navigation with Mobile Menu

```blade
<!-- Desktop Navigation -->
<nav class="navbar navbar-expand-md navbar-light bg-light">
    <div class="container-fluid">
        <a class="navbar-brand" href="/">KAAGAPAY</a>
        
        <!-- Hamburger button (mobile only) -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <!-- Navigation links (collapse on mobile) -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/dashboard">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/profile">Profile</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
```

---

## 9. Spacing - Responsive Utilities

### 9.1 Responsive Margins & Padding

```blade
<!-- Mobile: small spacing, tablet+: larger spacing -->
<div class="p-2 p-md-3 p-lg-4">
    <!-- Responsive padding -->
</div>

<!-- Different margins on mobile vs desktop -->
<div class="mb-3 mb-md-4 mb-lg-5">
    <!-- Responsive margin-bottom -->
</div>

<!-- Gap between flex items: responsive -->
<div class="d-flex flex-column flex-md-row gap-2 gap-md-3">
    <!-- Items with responsive gap -->
</div>
```

### 9.2 Spacing Reference

```
p-1 to p-5     = padding (1rem to 5rem)
m-1 to m-5     = margin (1rem to 5rem)
px-* = horizontal padding
py-* = vertical padding
mx-* = horizontal margin
my-* = vertical margin
g-* = gap (flex/grid)

# Responsive: p-2 p-md-3 p-lg-4 means:
# Mobile: 0.5rem, Tablet: 1rem, Desktop: 1.5rem
```

---

## 10. Images & Media - Responsive

### 10.1 Responsive Images

```blade
<!-- Responsive image that scales with container -->
<img src="image.jpg" alt="Description" class="img-fluid">

<!-- Picture element for art direction -->
<picture>
    <source media="(min-width: 768px)" srcset="large.jpg">
    <img src="small.jpg" alt="Description" class="img-fluid">
</picture>

<!-- Responsive video/iframe -->
<div class="ratio ratio-16x9">
    <iframe src="https://example.com/video" title="Video"></iframe>
</div>
```

---

## 11. Containers - Responsive Width

```blade
<!-- Fluid container (full width minus gutters) -->
<div class="container-fluid">
    <!-- Full width, responsive padding -->
</div>

<!-- Fixed-width container (responsive breakpoint widths) -->
<div class="container">
    <!-- Max-width: 540px (sm), 720px (md), 960px (lg), 1140px (xl), 1320px (xxl) -->
</div>

<!-- Custom max-width -->
<div class="container-lg">
    <!-- Max-width at LG breakpoint -->
</div>
```

---

## 12. Testing Responsive Design

### 12.1 Browser DevTools Testing

1. **Chrome/Edge DevTools:**
   - Press F12 → Toggle Device Toolbar
   - Test at: 375px (iPhone), 768px (Tablet), 1024px (Desktop)

2. **Firefox DevTools:**
   - Press F12 → Responsive Design Mode
   - Test multiple breakpoints

3. **Safari DevTools:**
   - Develop → Enter Responsive Design Mode
   - Test various sizes

### 12.2 Responsive Testing Checklist

- [ ] Mobile (< 576px): All content readable, single column
- [ ] Tablet (768px-1024px): Proper grid layout, navigation accessible
- [ ] Desktop (> 1024px): Full layout with sidebar/multi-column
- [ ] Text readable at all sizes
- [ ] Images scale properly
- [ ] Forms single column or multi-column appropriately
- [ ] Buttons/links accessible and tappable (min 44px)
- [ ] No horizontal scrolling on mobile
- [ ] Animations/transitions smooth
- [ ] Touch targets sufficient on mobile (min 48x48px)

---

## 13. Performance - Mobile Optimization

### 13.1 Mobile Performance Tips

✅ **DO:**
- Minimize image file sizes
- Use responsive images with `srcset`
- Lazy load images (`loading="lazy"`)
- Minimize CSS/JavaScript
- Cache static assets
- Use mobile-friendly fonts
- Compress assets

❌ **DON'T:**
- Load high-resolution images on mobile
- Use fixed layouts
- Forget viewport meta tag
- Use heavy frameworks unnecessarily
- Auto-play videos with sound
- Use non-optimized assets

### 13.2 Viewport Meta Tag

All templates should include:
```html
<meta name="viewport" content="width=device-width, initial-scale=1">
```

---

## 14. Real-World Examples

### 14.1 Dashboard - Responsive Layout

**Mobile (< 576px):**
- Single column layout
- Stacked cards
- Full-width buttons

**Tablet (576px - 992px):**
- 2-column grid for cards
- Sidebar collapsed into button
- 50% width form fields

**Desktop (> 992px):**
- 3-4 column grid
- Visible sidebar
- Full form layout with multiple columns

```blade
<div class="row g-3">
    <!-- Single column on mobile, 2 on tablet, 3 on desktop -->
    <div class="col-12 col-md-6 col-lg-4">
        <?= view('components/data_card', [...]) ?>
    </div>
</div>
```

### 14.2 Payment Form - Responsive

```blade
<div class="row g-3">
    <!-- Email: full width mobile, half tablet, third desktop -->
    <div class="col-12 col-md-6 col-lg-4">
        <?= view('components/form_field', [...]) ?>
    </div>
    
    <!-- Amount: full width mobile, half tablet, third desktop -->
    <div class="col-12 col-md-6 col-lg-4">
        <?= view('components/form_field', [...]) ?>
    </div>
    
    <!-- Date: full width everywhere -->
    <div class="col-12">
        <?= view('components/form_field', [...]) ?>
    </div>
    
    <!-- Buttons: stack on mobile, inline on tablet -->
    <div class="col-12">
        <div class="d-flex flex-column flex-md-row gap-2">
            <button class="btn btn-primary">Submit</button>
            <button class="btn btn-secondary">Cancel</button>
        </div>
    </div>
</div>
```

---

## 15. Implementation Checklist

Before deploying responsive updates:

- [ ] All breakpoints tested (XS, SM, MD, LG, XL, XXL)
- [ ] Grid system properly applied
- [ ] Mobile-first approach used
- [ ] Images responsive with `img-fluid`
- [ ] Forms responsive with proper grid
- [ ] Navigation accessible on mobile
- [ ] Display utilities (d-none, d-md-block) used correctly
- [ ] Spacing utilities responsive
- [ ] Tables scrollable on mobile
- [ ] Buttons/links have minimum touch targets (44px)
- [ ] No horizontal scrolling on mobile
- [ ] Text readable at all sizes
- [ ] Performance optimized for mobile
- [ ] Viewport meta tag present
- [ ] Tested on real devices (not just browser DevTools)
- [ ] Accessibility maintained on all sizes

---

## 16. Resources

- [Bootstrap 5 Responsive Design](https://getbootstrap.com/docs/5.0/getting-started/introduction/)
- [Bootstrap Grid System](https://getbootstrap.com/docs/5.0/layout/grid/)
- [Bootstrap Utilities](https://getbootstrap.com/docs/5.0/utilities/api/)
- [W3C Mobile Web Best Practices](https://www.w3.org/TR/mobile-bp/)

---

**Created:** May 8, 2026  
**Version:** 1.0  
**Status:** Active  
**Maintainer:** Development Team
