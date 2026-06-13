# haydenn.co.za + Gamified Running App Spec Pack

Created: 2026-06-12

This folder contains the split project specs for:

1. **haydenn.co.za** — a personal showcase website for **hayden**
2. **Project Trailbound** — a real usable gamified running web app built around Strava-first activity import, fantasy RPG progression, and Path of Exile-style systems

The spec is written for AI-assisted development. The goal is that you can open this folder, feed the relevant file to an AI coding agent, and start building without having to explain the whole thing again.

## Hard decisions already made

- The personal site is not a CV site.
- The personal site is a cool personal portfolio/showcase.
- The site should use the name **hayden**.
- No face/photo. Use logo, avatar, HUD, cinematic UI.
- No company names and no employer references.
- Only one showcased project for now: **the gamified running app**.
- Visual style: **cyberpunk + Apple premium + game HUD**.
- Site structure: one-page interactive scroll experience where each scroll section feels like a new page/scene.
- Running app should be real and usable, not just a mockup.
- Running app should be web-based first.
- Running app should use **Strava first** for run data import.
- Garmin integration is a future stretch goal because Garmin API access normally requires approval.
- Backend preference: **PHP + MySQL**, practical for Afrihost/cPanel-style hosting.
- Map provider preference: Mapbox because it is highly visual, cinematic, and game-friendly.
- Build style: AI coding with detailed specs and phased delivery.

## Recommended reading order

1. `01_personal_site_brief.md`
2. `02_personal_site_design_animation_spec.md`
3. `03_personal_site_technical_spec.md`
4. `04_trailbound_product_brief.md`
5. `05_trailbound_game_systems_spec.md`
6. `06_trailbound_technical_spec.md`
7. `07_database_schema.md`
8. `08_api_endpoints.md`
9. `09_build_roadmap.md`
10. `10_ai_coding_master_prompt.md`
11. `11_deployment_afrihost_cpanel.md`

## Suggested project structure

```text
haydenn/
  docs/
    specs/
      these markdown files
  public_html/
    index.html
    assets/
    app/
    api/
  private/
    .env
    logs/
    storage/
```

## External docs checked

These are included to keep the spec grounded in current public platform constraints:

- Strava API Getting Started: https://developers.strava.com/docs/getting-started/
- Strava API Authentication: https://developers.strava.com/docs/authentication/
- Strava API Webhooks: https://developers.strava.com/docs/webhooks/
- Strava API Rate Limits: https://developers.strava.com/docs/rate-limits/
- Garmin Connect Developer Program: https://developer.garmin.com/gc-developer-program/
- Garmin Activity API: https://developer.garmin.com/gc-developer-program/activity-api/
- Mapbox Access Tokens: https://docs.mapbox.com/help/dive-deeper/access-tokens/
- Mapbox GL JS Docs: https://docs.mapbox.com/mapbox-gl-js/guides/
