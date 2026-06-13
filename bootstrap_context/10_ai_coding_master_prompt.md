# 10 — AI Coding Master Prompt

Use this prompt when starting a fresh AI coding chat.

```text
You are helping me build haydenn.co.za and Project Trailbound.

This is an AI-assisted build. I need you to act like a senior full-stack developer, product designer, and game systems designer. Be practical, direct, and implementation-focused.

Main context:

I own haydenn.co.za and it is hosted on Afrihost/cPanel-style hosting. I primarily code in PHP and MySQL. The backend should be PHP/MySQL unless there is a very strong reason not to. The site must be deployable to shared hosting without requiring a long-running Node server.

There are two linked projects:

1. haydenn.co.za personal site
2. Project Trailbound gamified running app

Personal site requirements:

- Public name: hayden
- Tagline: dev who enjoys games and building cool things
- Purpose: portfolio/cool personal site, not a CV site
- No downloadable CV
- No face/photo
- Use logo/avatar/HUD identity
- No employer names
- No company names
- Only one showcased project: Trailbound
- Visual style: cyberpunk + Apple premium + game HUD
- One-page interactive scrolling site
- Each scroll section should feel like a new scene/page
- Must feel cinematic, polished, developer-oriented, and game-like
- Use tasteful animations: light trails, HUD overlays, button shatter, project reveal, skill-tree style visual
- Keep it smooth, premium, and not gimmicky

Trailbound requirements:

- Real usable web app, not just a mockup
- Fantasy RPG adventurer theme
- Path of Exile-style progression inspiration
- User runs using Strava first
- User comes back to the web app and sees XP, quests, loot, area unlocks, skill points, and progression
- Do not build live GPS tracking in browser for MVP
- Use Strava OAuth and activity import first
- Garmin direct integration is future/stretch
- Use Mapbox for maps and route visuals
- Backend PHP + MySQL
- Frontend can be HTML/CSS/JS with optional Vite build
- Needs account system, Strava connect, run import, XP, quests, rewards, inventory, world map, skill tree

Important build constraints:

- Shared hosting/cPanel-compatible
- No permanent Node server requirement
- Secrets must not be exposed in frontend
- Use PDO prepared statements
- Use CSRF protection for state-changing requests
- Use sessions securely
- Store Strava tokens server-side only
- Route data private by default

Your job:

1. Read the spec files I provide.
2. Do not invent employer/work-project content.
3. Do not add extra portfolio projects.
4. Start with a clean implementation plan.
5. Ask only necessary questions.
6. Then produce code in small, testable chunks.
7. Keep the design polished from the start.
8. Do not rush into building every feature.
9. Always explain where files go.
10. For each coding step, give exact file paths and full code for changed files.

Start by helping me build Phase 1:
- Personal site static structure
- CSS design system
- Scroll scenes
- Hero boot animation
- Trailbound project teaser
- Responsive layout
- Reduced motion support

Before coding, propose the file structure and confirm it is cPanel-friendly.
```

## Prompt for personal site only

```text
Build only the haydenn.co.za personal landing page first.

Requirements:
- Name: hayden
- Tagline: dev who enjoys games and building cool things
- Cyberpunk + Apple premium + game HUD style
- One-page scroll scenes
- No face/photo
- Logo/avatar based
- No employer references
- Only project showcased: Trailbound
- Static deployable to Afrihost/cPanel
- Use HTML/CSS/JS
- Add tasteful cinematic animation
- Add reduced motion support
- Make it responsive

Give me:
1. File structure
2. Full code for index.html
3. Full code for CSS
4. Full code for JS
5. How to upload/test it
```

## Prompt for Trailbound MVP only

```text
Build the Trailbound MVP backend and app shell.

Requirements:
- PHP + MySQL
- PDO
- Sessions
- Register/login/logout
- Strava OAuth connection
- Manual Strava sync
- Store imported run activities
- Calculate XP
- Complete starter quest
- Grant a chest
- Unlock first region
- Dashboard UI
- World map UI placeholder
- Inventory UI
- Skill tree UI placeholder

Use the database schema from 07_database_schema.md.
Use the API endpoints from 08_api_endpoints.md.

Give me code in small steps. Do not dump the entire app at once.
Start with database bootstrap, config, auth, and dashboard skeleton.
```
