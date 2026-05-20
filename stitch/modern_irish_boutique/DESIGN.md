---
name: Modern Irish Boutique
colors:
  surface: '#121212'
  surface-dim: '#071a12'
  surface-bright: '#1A1A1A'
  surface-container-lowest: '#0c0f10'
  surface-container-low: '#191c1e'
  surface-container: '#1d2022'
  surface-container-high: '#282a2c'
  surface-container-highest: '#323537'
  on-surface: '#e1e2e4'
  on-surface-variant: '#d0c5af'
  inverse-surface: '#e1e2e4'
  inverse-on-surface: '#2e3132'
  outline: '#99907c'
  outline-variant: '#4d4635'
  surface-tint: '#e9c349'
  primary: '#f2ca50'
  on-primary: '#3c2f00'
  primary-container: '#d4af37'
  on-primary-container: '#554300'
  inverse-primary: '#735c00'
  secondary: '#b0ceb7'
  on-secondary: '#1c3625'
  secondary-container: '#354f3d'
  on-secondary-container: '#a2c0a9'
  tertiary: '#d0cecd'
  on-tertiary: '#313030'
  tertiary-container: '#b5b2b2'
  on-tertiary-container: '#454545'
  error: '#ffb4ab'
  on-error: '#690005'
  error-container: '#93000a'
  on-error-container: '#ffdad6'
  primary-fixed: '#ffe088'
  primary-fixed-dim: '#e9c349'
  on-primary-fixed: '#241a00'
  on-primary-fixed-variant: '#574500'
  secondary-fixed: '#ccead2'
  secondary-fixed-dim: '#b0ceb7'
  on-secondary-fixed: '#062012'
  on-secondary-fixed-variant: '#324c3b'
  tertiary-fixed: '#e5e2e1'
  tertiary-fixed-dim: '#c9c6c5'
  on-tertiary-fixed: '#1c1b1b'
  on-tertiary-fixed-variant: '#474646'
  background: '#111415'
  on-background: '#e1e2e4'
  surface-variant: '#323537'
  status-live: '#22C55E'
  status-closed: '#EF4444'
  text-muted: '#9CA3AF'
typography:
  display-lg:
    fontFamily: Playfair Display
    fontSize: 48px
    fontWeight: '700'
    lineHeight: 56px
    letterSpacing: -0.01em
  display-lg-mobile:
    fontFamily: Playfair Display
    fontSize: 36px
    fontWeight: '700'
    lineHeight: 44px
    letterSpacing: -0.01em
  headline-md:
    fontFamily: Playfair Display
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
  title-sm:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
  body-base:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-muted:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  label-caps:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '600'
    lineHeight: 16px
    letterSpacing: 0.05em
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  base: 4px
  xs: 4px
  sm: 8px
  md: 16px
  lg: 24px
  xl: 32px
  section-mobile: 48px
  section-desktop: 80px
  gutter: 16px
  margin-safe: 16px
---

## Brand & Style

The design system embodies a "Modern Irish Boutique" aesthetic, moving away from cluttered, traditional pub cliches toward a sophisticated, atmospheric nightlife experience. The brand personality is prestigious yet welcoming, balancing the rugged heritage of an Irish sports pub with the refined elegance of a Parisian cocktail lounge.

The visual style is a fusion of **Minimalism** and **High-Contrast Dark Mode**. It utilizes deep, matte foundations and "layered darkness" to create a moody, high-end environment. Visual interest is generated through:
- **Atmospheric Depth:** Using forest green overlays and subtle tonal shifts rather than flat blacks.
- **Refined Accents:** Brushed gold highlights that mimic polished brass and glowing spirits.
- **Typography-led Hierarchy:** A stark contrast between traditional serif elegance and modern functional sans-serifs.
- **Tactile Details:** Large, tappable interactive elements designed for low-light environments and mobile-first utility.

## Colors

The color strategy relies on a strict dark-mode palette to maintain a consistent "nightlife" atmosphere regardless of the time of day. 

- **Primary (Brushed Amber Gold):** Used exclusively for high-priority interactive elements, price highlights, and brand accents. It should feel like glowing brass.
- **Secondary (Deep Forest Green):** The heritage anchor. Used for section backgrounds and semi-transparent overlays on photography to maintain brand presence across imagery.
- **Neutral (Off-White):** Used for typography to ensure maximum legibility against dark surfaces without the clinical harshness of pure white.
- **Surfaces:** A tier of dark grays (`#121212` to `#1A1A1A`) provides structural depth, separating cards and navigation from the matte black foundation.
- **Semantic Colors:** Neon-inspired Green and Red are reserved for real-time status indicators (Live Match, Pub Open/Closed).

## Typography

The typography pairs the high-contrast elegance of **Playfair Display** for brand storytelling and the utilitarian precision of **Inter** for functional UI.

- **Display & Headlines:** Always set in Playfair Display. Use Gold for the largest display levels to establish the boutique feel. On mobile, the Display Large size scales down to 36px to ensure it remains within the viewport without breaking.
- **Body & Metadata:** Set in Inter. This provides a neutral, readable counterpoint to the decorative serif.
- **Labels:** Uppercase Inter with increased letter spacing is used for buttons, navigation, and badges to create a tactile, "UI-first" feel.
- **Legibility Note:** Because light text on dark backgrounds can "bloom," avoid using font weights below 400 for body copy.

## Layout & Spacing

This design system uses a **Fluid Grid** model with a maximum content width of 1152px (max-w-6xl). The system prioritizes "breathing room" to maintain a premium feel.

- **Mobile (Default):** 1-column layout with 16px side margins. Cards and items stack vertically. Large interactive targets (48px+ height) are mandatory.
- **Desktop (Breakpoint 1024px):** Transitions to a 12-column grid. Menu categories reflow into a 2-column grid, and match/event listings reflow into 3-column grids.
- **Vertical Rhythm:** Sections are separated by significant vertical padding (48px on mobile / 80px on desktop) to establish a clear editorial flow.
- **Gutters:** A consistent 16px or 24px gutter is used between cards to ensure they are perceived as distinct objects.

## Elevation & Depth

Visual hierarchy is achieved through **Tonal Layers** rather than heavy shadows, ensuring the design remains "flat but deep."

- **Layer 0 (Background):** Matte Black (`#0D0D0D`).
- **Layer 1 (Surfaces):** Dark Grey (`#121212`) cards and sections sit directly on the background.
- **Layer 2 (Interactive):** Elements that are hovered or active use a subtle brightness shift (shifting towards `#1A1A1A`) or a thin gold outline.
- **Overlays:** Sticky navigation uses a backdrop-blur (12px) effect with a semi-transparent surface-bright color to maintain context of the content scrolling beneath it.
- **Accents:** Depth is further reinforced by using the Forest Green secondary color as a subtle gradient wash behind text sections to pull them forward from the black void.

## Shapes

The shape language is **Rounded**, conveying a modern and approachable boutique feel.

- **Standard Elements:** Buttons and small input fields use a 0.5rem (8px) radius.
- **Cards & Containers:** Use `rounded-lg` (16px) or `rounded-xl` (24px) for larger match cards and event blocks to soften the high-contrast layout.
- **Badges:** "Live" or "Status" indicators use the **Pill-shaped** (full) roundedness to stand out as distinct functional tokens.

## Components

- **Buttons:**
  - **Primary:** Solid Brushed Amber Gold background, black text, bold Inter. Transition to a slightly brighter gold on hover.
  - **Secondary:** Transparent background, 1px gold border, gold text.
- **Cards (Match/Event):**
  - Dark Grey surface (`#121212`), 16px padding, 16px rounded corners.
  - On hover: Scale up 2% and add a thin gold border.
- **Input Fields:**
  - Dark surface with a subtle 1px border (`rgba(255,255,255,0.1)`). On focus, the border transitions to Brushed Amber Gold.
- **Menu Items:**
  - Horizontal flex layout with a thin gold Art-Deco style divider. 
  - Happy Hour prices are highlighted in bold gold, with the regular price crossed out in muted slate text.
- **Status Badges:**
  - Small pill-shaped indicators. "LIVE" matches should include a small glowing animation effect (box-shadow pulse) in Neon Green.
- **Sticky Navbar:**
  - Height: 72px. Background: `#1A1A1A` with backdrop-blur. Logo on the left, navigation links on the right using uppercase labels.