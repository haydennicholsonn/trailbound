# 02 — Personal Site Design + Animation Spec

## Design summary

Build a cinematic one-page portfolio that feels like entering a polished game interface.

Keywords:

- Premium
- Cinematic
- Game HUD
- Cyberpunk
- Clean
- Fast
- Interactive
- Dark
- Sharp
- Playful but not childish

## Visual principles

### 1. Dark base, controlled glow

Use a dark base UI with restrained glow effects.

Recommended palette:

```css
--bg: #05070d;
--bg-soft: #0b1020;
--panel: rgba(15, 23, 42, 0.64);
--panel-border: rgba(148, 163, 184, 0.18);
--text: #f8fafc;
--text-muted: #94a3b8;
--accent-blue: #38bdf8;
--accent-purple: #8b5cf6;
--accent-green: #34d399;
--danger-red: #fb315f;
--gold: #facc15;
```

Do not make every element neon. Use glow for:

- Primary button hover
- Scene transitions
- Logo/avatar moments
- Project reveal
- Map unlock animation

### 2. Apple premium spacing

Even though the theme is cyber/game/HUD, layout should feel spacious and premium.

Rules:

- Large hero type
- Big vertical spacing
- Few words per screen
- No cluttered panels
- Maximum 2 primary focus elements per scene
- Smooth typography scale
- Consistent gutters

### 3. Game HUD layering

Add HUD elements in the background and corners:

- Tiny corner brackets
- Thin scan lines
- Soft grid
- Coordinates-like text
- Scene label
- Progress rail
- “System online” indicator
- Player profile card
- Dossier labels

These should be subtle.

## Typography

Recommended approach:

- Display font: modern, sharp, slightly sci-fi
- Body font: clean sans-serif

Free Google Font candidates:

- Space Grotesk
- Inter
- Orbitron only for small HUD labels, not full paragraphs
- Rajdhani for game-like labels
- Sora for polished modern feel

Recommended pairing:

```text
Headings: Space Grotesk
Body: Inter
HUD labels: Rajdhani or Space Grotesk uppercase
```

## Logo/avatar direction

No face.

Create a simple identity system:

### Logo idea

A lowercase **h** or **H** monogram made from:

- Circuit lines
- Small pixel/rune cuts
- A diagonal slash
- Soft neon edge

### Avatar idea

A hooded fantasy/dev avatar silhouette, but abstract enough not to look like a real portrait.

Possible avatar description:

```text
A minimal dark fantasy developer avatar: hooded silhouette, glowing blue visor, subtle rune/circuit details, clean vector style, no face, premium game UI look.
```

The avatar should be used sparingly:

- Hero scene
- Player profile
- Loading/boot sequence
- Tiny icon in nav/HUD

## Page interaction model

The site is technically one page, but scroll creates scene transitions.

Recommended scroll model:

- Full viewport sections using `min-height: 100vh`
- Scroll snap optional, but avoid making it annoying
- Use smooth scrolling carefully
- Each section animates in when it enters viewport
- Background changes slightly per section
- Progress indicator shows current scene

Scenes:

```text
00_BOOT
01_PROFILE
02_SKILL_TREE
03_PROJECT_DOSSIER
04_TRAILBOUND
05_CONTACT
```

## Animation library recommendations

For polish:

- GSAP for cinematic scene animations
- ScrollTrigger for scroll-based transitions
- Three.js only if needed for subtle particles or background objects
- Lenis for smooth scroll if performance is good
- CSS transitions for normal hover states

Keep it deployable as static JS files.

## Required animations

### 1. Boot sequence

On first load:

```text
> booting haydenn.co.za
> loading profile
> compiling project dossier
> entering showcase
```

Animation:

- Text types in
- HUD grid fades up
- Logo appears
- Hero copy fades/slides in
- Button appears last

Duration:

- 1.5 to 2.5 seconds max
- Add “skip intro” if longer than 2 seconds

### 2. Light trail cursor

Desktop only.

Mouse movement creates subtle trailing particles or light streaks.

Rules:

- Must be lightweight
- Disable on mobile
- Respect reduced motion
- Should not block clicks
- Should decay quickly

### 3. Button shatter

For primary buttons:

On click:

- Button briefly compresses
- Border flashes
- Button breaks into small square/pixel pieces
- Pieces move outward and fade
- Scrolls/navigates after 250–500ms

Use this only on key CTA buttons, not every button.

### 4. Project card reveal

Featured project card should feel like selecting a mission.

States:

- Idle: glass panel with subtle glow
- Hover: border glow, background map/rune animation
- Active: card expands or transitions to project scene
- Click: dossier opens with scanline wipe

### 5. Skill tree reveal

Skills should appear like unlocked nodes.

Animation:

- Nodes fade in
- Lines draw between nodes
- Current nodes glow
- Locked/future nodes remain dim

Do not make this a real complicated skill tree yet. It is a visual metaphor for the personal site.

### 6. Trailbound map unlock teaser

In the featured project scene:

- A dark fantasy map tile is hidden by fog
- After the user scrolls into section, light reveals a region
- Show text: `AREA DISCOVERED: The First Road`

This connects the personal site to the running app concept.

## Reduced motion support

Must implement:

```css
@media (prefers-reduced-motion: reduce) {
  * {
    animation-duration: 0.001ms !important;
    animation-iteration-count: 1 !important;
    scroll-behavior: auto !important;
  }
}
```

Also disable:

- Particle trails
- Long boot animations
- Continuous parallax
- Heavy canvas effects

## Mobile behaviour

Mobile should still feel premium.

Mobile changes:

- Disable cursor trail
- Reduce particle count
- Use normal vertical scrolling
- Keep section animations simple
- Buttons should not require hover
- Project cards stack cleanly
- HUD overlays should not crowd content

## Performance requirements

Target:

- 60fps on a normal desktop
- Smooth enough on modern phones
- Total initial JS payload should be reasonable
- Lazy load non-critical animations
- Avoid massive 3D scenes
- Avoid unoptimized video backgrounds

## Do not do

- Do not add random animations everywhere.
- Do not make text unreadable.
- Do not use glitch effects constantly.
- Do not use loud autoplay audio.
- Do not make the site look like a crypto landing page.
- Do not make the site feel like a template.
