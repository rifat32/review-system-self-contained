# Centralized CSS Design System

This document explains how to use the centralized CSS design system across your Laravel project.

## 📁 **File Structure**

```
public/css/
├── variables.css    # Central color variables, spacing, typography
└── common.css       # Reusable components (buttons, cards, grids, etc.)
```

## 🚀 **Quick Start**

### **1. Include in Your Blade Files**

Add these two lines in your `<head>` section **before any custom styles**:

```html
<!-- Centralized CSS Design System -->
<link rel="stylesheet" href="{{ asset('css/variables.css') }}" />
<link rel="stylesheet" href="{{ asset('css/common.css') }}" />
```

### **2. Use CSS Variables**

All design tokens are available as CSS variables:

```css
/* Example: Custom component */
.my-component {
    background: var(--bg-card);
    color: var(--text-primary);
    padding: var(--spacing-md);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-md);
}
```

## 🎨 **Available Variables**

### **Colors**

```css
/* Primary */
--primary, --primary-dark, --primary-light, --primary-lighter

/* Secondary */
--secondary, --secondary-dark, --secondary-light, --secondary-lighter

/* Danger/Error */
--danger, --danger-dark, --danger-light, --danger-lighter

/* Warning */
--warning, --warning-dark, --warning-light, --warning-lighter

/* Info */
--info, --info-dark, --info-light, --info-lighter

/* Success */
--success, --success-dark, --success-light, --success-lighter

/* Backgrounds */
--bg-primary, --bg-secondary, --bg-tertiary, --bg-card

/* Text */
--text-primary, --text-secondary, --text-tertiary
```

### **Gradients**

```css
--gradient-bg, --gradient-primary, --gradient-secondary, --gradient-danger
```

### **Spacing**

```css
--spacing-xs   /* 8px */
--spacing-sm   /* 16px */
--spacing-md   /* 24px */
--spacing-lg   /* 32px */
--spacing-xl   /* 48px */
--spacing-2xl  /* 64px */
--spacing-3xl  /* 96px */
```

### **Typography**

```css
/* Font Sizes */
--text-xs, --text-sm, --text-base, --text-lg, --text-xl, --text-2xl, --text-3xl, --text-4xl, --text-5xl, --text-6xl

/* Font Weights */
--font-light, --font-regular, --font-medium, --font-semibold, --font-bold

/* Line Heights */
--leading-tight, --leading-normal, --leading-relaxed, --leading-loose
```

### **Border Radius**

```css
--radius-sm, --radius-md, --radius-lg, --radius-xl, --radius-full
```

### **Shadows**

```css
--shadow-xs, --shadow-sm, --shadow-md, --shadow-lg, --shadow-xl
--shadow-primary, --shadow-secondary, --shadow-danger
```

## 🧩 **Pre-Built Components**

### **Buttons**

```html
<a href="#" class="btn btn-primary">Primary Button</a>
<a href="#" class="btn btn-secondary">Secondary Button</a>
<a href="#" class="btn btn-danger">Danger Button</a>
<a href="#" class="btn btn-outline">Outline Button</a>

<!-- Sizes -->
<button class="btn btn-primary btn-sm">Small</button>
<button class="btn btn-primary">Default</button>
<button class="btn btn-primary btn-lg">Large</button>

<!-- Full Width -->
<button class="btn btn-primary btn-full">Full Width</button>
```

### **Cards**

```html
<div class="card">
    <div class="card-header">
        <h3>Card Title</h3>
    </div>
    <div class="card-body">
        <p>Card content goes here</p>
    </div>
    <div class="card-footer">
        <button class="btn btn-primary">Action</button>
    </div>
</div>
```

### **Grid Layouts**

```html
<!-- Auto-fit grid (responsive) -->
<div class="grid grid-auto">
    <div class="card">Item 1</div>
    <div class="card">Item 2</div>
    <div class="card">Item 3</div>
</div>

<!-- Fixed columns -->
<div class="grid grid-cols-3">
    <div>Column 1</div>
    <div>Column 2</div>
    <div>Column 3</div>
</div>
```

### **Badges**

```html
<span class="badge badge-primary">Primary</span>
<span class="badge badge-secondary">Success</span>
<span class="badge badge-danger">Error</span>
<span class="badge badge-warning">Warning</span>
```

### **Utility Classes**

```html
<!-- Text Alignment -->
<div class="text-center">Centered text</div>
<div class="text-left">Left aligned</div>
<div class="text-right">Right aligned</div>

<!-- Flexbox -->
<div class="flex items-center justify-between gap-md">
    <span>Left</span>
    <span>Right</span>
</div>

<!-- Spacing -->
<div class="mb-md">Margin bottom medium</div>
<div class="mt-lg">Margin top large</div>

<!-- Animations -->
<div class="animate-fade-in">Fade in animation</div>
<div class="animate-fade-in-up">Fade in up animation</div>
```

## 🎯 **Best Practices**

### **✅ DO**

```css
/* Use CSS variables for consistency */
.custom-card {
    background: var(--bg-card);
    padding: var(--spacing-md);
    color: var(--text-primary);
}

/* Use pre-built components */
<button class="btn btn-primary">Click Me</button>
```

### **❌ DON'T**

```css
/* Don't hardcode colors */
.bad-card {
    background: #1e293b;  /* ❌ */
    padding: 24px;        /* ❌ */
    color: #f1f5f9;       /* ❌ */
}

/* Don't reinvent components */
<button style="background: blue; padding: 10px;">Bad</button>
```

## 🌓 **Light Theme Support (Future)**

To enable light theme, uncomment the light theme section in `variables.css` and add:

```html
<body data-theme="light"></body>
```

## 📝 **Example: Complete Page**

```html
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>My Page</title>

        <!-- Centralized CSS -->
        <link rel="stylesheet" href="{{ asset('css/variables.css') }}" />
        <link rel="stylesheet" href="{{ asset('css/common.css') }}" />

        <style>
            /* Only page-specific styles here */
            .hero {
                padding: var(--spacing-xl);
                background: var(--gradient-primary);
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="grid grid-auto">
                <div class="card">
                    <h3>Card Title</h3>
                    <p>Card content using centralized design system!</p>
                    <button class="btn btn-primary">Action</button>
                </div>
            </div>
        </div>
    </body>
</html>
```

## 🔄 **Updating Colors Globally**

To change colors across **all pages**, just update `public/css/variables.css`:

```css
:root {
    --primary: #your-new-color; /* Changes everywhere! */
}
```

No need to update individual files! 🎉

## 📚 **Reference**

-   **Variables**: [`public/css/variables.css`](file:///e:/review-system-self-contained/public/css/variables.css)
-   **Components**: [`public/css/common.css`](file:///e:/review-system-self-contained/public/css/common.css)
-   **Example Usage**: [`resources/views/welcome.blade.php`](file:///e:/review-system-self-contained/resources/views/welcome.blade.php)
