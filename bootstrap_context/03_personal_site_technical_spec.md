# 03 — Personal Site Technical Spec

## Recommended stack

Use a frontend-first static site for the personal landing page.

Recommended:

```text
Frontend:
- HTML
- CSS
- JavaScript
- Optional Vite build step
- GSAP for animations
- Mapbox only if using a live map preview
- No heavy backend required for the landing page

Backend:
- PHP only for contact form or API passthrough if needed
- MySQL not required for landing page MVP
```

Because this is hosted through Afrihost/cPanel-style hosting, avoid needing a long-running Node server.

## Suggested architecture

```text
public_html/
  index.html
  assets/
    css/
      main.css
    js/
      main.js
      animations.js
      cursor-trail.js
      button-shatter.js
    img/
      logo.svg
      avatar.svg
      noise.webp
      map-preview.webp
  app/
    index.html              # Trailbound app shell later
  api/
    contact.php             # optional
```

If using Vite locally:

```text
src/
  main.ts
  styles/
  components/
dist/
  index.html
  assets/
```

Deploy the built `dist` contents to `public_html`.

## MVP landing page files

Minimum file list:

```text
index.html
assets/css/main.css
assets/js/main.js
assets/js/animations.js
assets/js/effects.js
assets/img/logo.svg
assets/img/avatar.svg
```

## HTML section structure

```html
<body>
  <div id="boot-screen"></div>
  <canvas id="cursor-trail"></canvas>

  <main id="site-shell">
    <section id="hero" data-scene="00_BOOT"></section>
    <section id="profile" data-scene="01_PROFILE"></section>
    <section id="skills" data-scene="02_SKILL_TREE"></section>
    <section id="project" data-scene="03_PROJECT_DOSSIER"></section>
    <section id="trailbound" data-scene="04_TRAILBOUND"></section>
    <section id="contact" data-scene="05_CONTACT"></section>
  </main>
</body>
```

## JavaScript modules

### `main.js`

Responsibilities:

- Initialise site
- Detect reduced motion
- Initialise scene observer
- Initialise animations only if allowed
- Bind CTA buttons

### `animations.js`

Responsibilities:

- Boot animation
- Section entrance animations
- Scroll progress
- Project card reveal
- Skill node reveal

### `cursor-trail.js`

Responsibilities:

- Desktop-only cursor trail
- Canvas render loop
- Performance throttling
- Disable on mobile/reduced motion

### `button-shatter.js`

Responsibilities:

- Convert button bounds into small particles
- Animate particles out
- Fade particles
- Trigger callback after animation

## Scene observer

Use `IntersectionObserver` to determine active scene.

Pseudo-flow:

```js
const sections = document.querySelectorAll('[data-scene]');

const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      setActiveScene(entry.target.dataset.scene);
      entry.target.classList.add('is-visible');
    }
  });
}, { threshold: 0.55 });
```

## CSS layout rules

Use CSS custom properties.

```css
:root {
  --scene-padding: clamp(24px, 5vw, 80px);
  --max-width: 1180px;
}
```

Sections:

```css
.scene {
  min-height: 100vh;
  display: grid;
  place-items: center;
  padding: var(--scene-padding);
}
```

## Contact form

MVP should not require a contact form.

Use simple contact buttons:

- GitHub link, if wanted
- Email link, if wanted
- “Follow the build” link later

If adding a contact form later:

- PHP endpoint with CSRF token
- Rate limiting
- Honeypot field
- Server-side validation
- No public SMTP secrets in JS

## Security

For landing page:

- No secrets in frontend JS
- If using Mapbox public token, restrict it by URL in Mapbox dashboard
- Sanitize any form inputs server-side
- Use HTTPS only
- Add security headers if possible

Suggested headers via `.htaccess`:

```apache
Header always set X-Content-Type-Options "nosniff"
Header always set X-Frame-Options "SAMEORIGIN"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"
```

Do not set `geolocation=()` on pages that need Trailbound GPS later. Use it only on the landing page if separated.

## Deployment

### Simple deployment

Upload files to:

```text
public_html/
```

### If Vite is used

Build locally:

```bash
npm install
npm run build
```

Upload `dist/*` to `public_html`.

## SEO basics

Even though this is not a job-hunting CV site, it should still index cleanly.

Use:

```html
<title>hayden — dev who enjoys games and building cool things</title>
<meta name="description" content="Personal showcase for hayden — a developer building polished web experiences, game-inspired systems, and Project Trailbound.">
```

Open Graph:

```html
<meta property="og:title" content="hayden">
<meta property="og:description" content="dev who enjoys games and building cool things">
<meta property="og:type" content="website">
```

## MVP acceptance criteria

- Site loads on haydenn.co.za.
- Hero scene works.
- Scroll scenes work.
- Project focus is clear.
- Looks polished on desktop.
- Looks clean on mobile.
- No employer names.
- No CV button.
- No broken animations.
- Reduced motion support exists.
- The site does not require Node server hosting.
