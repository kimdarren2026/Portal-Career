---
name: Academic Career Nexus
colors:
  surface: '#ffffff'
  surface-dim: '#d7dadc'
  surface-bright: '#f7fafc'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f1f4f6'
  surface-container: '#ebeef0'
  surface-container-high: '#e5e9eb'
  surface-container-highest: '#e0e3e5'
  on-surface: '#181c1e'
  on-surface-variant: '#43474e'
  inverse-surface: '#2d3133'
  inverse-on-surface: '#eef1f3'
  outline: '#74777f'
  outline-variant: '#c4c6cf'
  surface-tint: '#455f88'
  primary: '#002045'
  on-primary: '#ffffff'
  primary-container: '#1a365d'
  on-primary-container: '#86a0cd'
  inverse-primary: '#adc7f7'
  secondary: '#0061a5'
  on-secondary: '#ffffff'
  secondary-container: '#66affe'
  on-secondary-container: '#004172'
  tertiary: '#2d1d00'
  on-tertiary: '#ffffff'
  tertiary-container: '#493100'
  on-tertiary-container: '#cb9524'
  error: '#E11B22'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#d6e3ff'
  primary-fixed-dim: '#adc7f7'
  on-primary-fixed: '#001b3c'
  on-primary-fixed-variant: '#2d476f'
  secondary-fixed: '#d2e4ff'
  secondary-fixed-dim: '#9fcaff'
  on-secondary-fixed: '#001d37'
  on-secondary-fixed-variant: '#00497e'
  tertiary-fixed: '#ffdeaa'
  tertiary-fixed-dim: '#f8bc4b'
  on-tertiary-fixed: '#271900'
  on-tertiary-fixed-variant: '#5f4100'
  background: '#f7fafc'
  on-background: '#181c1e'
  surface-variant: '#e0e3e5'
  success: '#38a169'
  deep-navy: '#1B2D4F'
typography:
  display-lg:
    fontFamily: Inter
    fontSize: 48px
    fontWeight: '700'
    lineHeight: 56px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
  headline-lg-mobile:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '700'
    lineHeight: 32px
  headline-md:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
  title-lg:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
  body-lg:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  label-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 20px
    letterSpacing: 0.01em
  label-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '500'
    lineHeight: 16px
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  base: 4px
  gutter: 24px
  margin-mobile: 16px
  margin-desktop: 48px
  container-max: 1280px
---

## Brand & Style

The design system is engineered to bridge the gap between academic prestige and professional opportunity. It embodies a **Corporate Modern** aesthetic—prioritizing clarity, authority, and reliability. The interface mimics the structure of high-end SaaS platforms, using generous white space and a modular layout to manage information density without overwhelming the student user.

The target audience consists of university students, alumni, and institutional partners. The emotional response should be one of "structured ambition": the platform feels like a serious tool for career progression, yet remains accessible and navigable through logical information architecture and clear visual cues.

## Colors

The palette is anchored in **Deep Navy (#1a365d)** to establish institutional trust and academic heritage. **Vibrant Blue (#3182ce)** serves as the primary action color, directing users toward key interactions like "Apply" or "Submit."

**Gold (#d69e2e)** is reserved exclusively for high-value accents, specifically for "Mitra" (Partner) verification badges and premium employer highlights. The background utilizes a very soft **Light Gray (#f7fafc)** to reduce eye strain and provide enough contrast for white surface cards to appear subtly elevated.

## Typography

This design system utilizes **Inter** for its exceptional legibility and neutral, professional tone. The scale is built on a clear hierarchy to handle data-heavy career listings.

- **Headlines:** Use tight letter-spacing for large displays to maintain a cohesive, "impactful" look.
- **Body Text:** Standard `body-md` (16px) is the workhorse for job descriptions, ensuring accessibility.
- **Labels:** Used for metadata, badges, and small UI hints. Always rendered with slightly higher weight (Medium or Semi-Bold) to ensure visibility against card backgrounds.

## Layout & Spacing

This design system follows a **12-column fixed grid** on desktop, centered within the viewport. The spacing rhythm is based on a 4px baseline, but defaults to 16px (4 units) and 24px (6 units) for most component internal spacing.

- **Desktop (1024px+):** 12 columns, 24px gutters, 48px side margins.
- **Tablet (768px - 1023px):** 8 columns, 20px gutters, 32px side margins.
- **Mobile (Up to 767px):** 4 columns, 16px gutters, 16px side margins.

Dashboard areas utilize a "sticky sidebar" layout (280px width) with a fluid content area to accommodate complex data tables and multi-step forms.

## Elevation & Depth

Visual hierarchy is achieved through **Tonal Layers** and extremely soft **Ambient Shadows**. This design system avoids heavy shadows to maintain a clean, academic feel.

- **Level 0 (Background):** #f7fafc.
- **Level 1 (Cards/Surface):** White (#ffffff) with a 1px border (#e2e8f0) or a soft shadow (Blur: 10px, Y: 4px, Color: 2% Black).
- **Level 2 (Hover/Active):** Slightly deeper shadow (Blur: 20px, Y: 8px, Color: 5% Primary) to indicate interactivity on job cards.
- **Overlays (Modals):** High-diffuse shadow with a 40% opacity black backdrop blur (4px) to keep the focus on the task at hand.

## Shapes

The shape language is consistently **Rounded** (8px/0.5rem base) to soften the "corporate" feel and make the platform more approachable for students.

- **Buttons & Inputs:** 8px (base)
- **Job Cards:** 16px (rounded-lg) for a modern, containerized look.
- **Badges/Chips:** Full pill (999px) for status indicators and job tags to distinguish them from interactive buttons.

## Components

### Job Vacancy Cards
- **Structure:** Left-aligned company logo (max-height 48px), followed by Title (title-lg) and Metadata (label-sm).
- **Badges:** Use the "Pill" shape. Apply the Primary Blue for job types (Full-time, Internship) and Gold for "Mitra Kampus" verification badges.
- **Interaction:** Entire card area is clickable, with a subtle 2px Y-axis lift on hover.

### Dashboard Sidebar
- **Styling:** Deep Navy background with white text (80% opacity for inactive items, 100% + active indicator for current page).
- **Icons:** Minimalist 24px line icons.

### Multi-Step Forms (Steppers)
- **Visuals:** Horizontal line connecting circular nodes. Active nodes use Primary Blue; completed nodes use Success Green with a check icon.
- **Progress:** A thin 4px progress bar should accompany long forms to provide immediate visual feedback.

### Search & Filter Bars
- **Design:** Single-field search with an integrated "Filter" icon button. Use white background with a subtle border to distinguish from the light gray page background.

### Verification Badges
- **Mitra Badge:** Gold icon with "Mitra Resmi" text in `label-sm`. Always placed next to the Company Name to validate the employer's relationship with the university.