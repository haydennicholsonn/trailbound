# 06 — Trailbound Technical Spec

## Recommended MVP architecture

Trailbound should be built as a web app with a PHP/MySQL backend.

### Core stack

```text
Frontend:
- HTML/CSS/JavaScript
- Optional Vite + TypeScript for maintainability
- Mapbox GL JS for maps
- GSAP for reward/quest animations

Backend:
- PHP 8.x
- MySQL / MariaDB
- PDO prepared statements
- Composer for dependencies if available
- Simple custom router or Slim PHP

Integrations:
- Strava OAuth2
- Strava API activity import
- Mapbox public token for client map rendering
```

## Why not live browser GPS for MVP?

The user wants a real usable app and is happy using Strava/Garmin.

So MVP should not depend on a browser tracking GPS while running.

Reasons:

- Web background GPS is inconsistent when the phone locks.
- Native apps handle run recording better.
- Strava/Garmin/watch apps already solve the run recording problem.
- Trailbound should focus on the game layer after the run.

## Integration flow

### Strava OAuth

1. User clicks `Connect Strava`.
2. Redirect to Strava OAuth authorisation.
3. User approves.
4. Strava redirects back with `code`.
5. Backend exchanges code for tokens.
6. Backend stores:
   - athlete ID
   - access token
   - refresh token
   - token expiry
7. User can now sync runs.

### Activity import

MVP manual sync:

1. User clicks `Sync Runs`.
2. Backend refreshes Strava token if expired.
3. Backend fetches recent activities.
4. Backend filters for run activities.
5. Backend inserts activities not already imported.
6. Backend processes each new activity through game engine.
7. Backend returns reward summary.

Phase 1.5 webhook sync:

1. Strava sends activity event to webhook callback.
2. Backend stores webhook event.
3. Cron/worker processes event.
4. New activity imported and rewards calculated.
5. User sees rewards next time they open app.

## Token storage

Tokens must not be exposed to JavaScript.

Store tokens server-side in MySQL.

Minimum:

- Encrypt tokens if possible using an app secret in `.env`
- Keep `.env` outside public_html if hosting allows
- Never commit tokens
- Never return access tokens in API responses

## Backend folder structure

```text
public_html/
  app/
    index.html
    assets/
  api/
    index.php
    strava/
      connect.php
      callback.php
      sync.php
      webhook.php
    auth/
      login.php
      register.php
      logout.php
    game/
      profile.php
      quests.php
      rewards.php
      open-chest.php
      skill-tree.php
      unlock-node.php
  private/
    config/
      bootstrap.php
      database.php
      env.php
    src/
      Auth/
      Strava/
      Game/
      Quest/
      Reward/
      Map/
      Security/
    logs/
    storage/
```

If private folders outside `public_html` are not possible, protect them with `.htaccess`.

## Frontend screens

### 1. Landing / intro

- Trailbound logo
- Cinematic game intro
- `Start Your Journey`
- `Connect Strava`

### 2. Auth

- Register
- Login
- Forgot password later

### 3. Onboarding

- Pick name
- Pick class
- Connect Strava
- See first quest

### 4. Dashboard

Main hub.

Shows:

- Character card
- Level/XP bar
- Current quest
- Latest run
- Area progress
- Chest inventory
- Sync button

### 5. World map

Shows:

- Fantasy map regions
- Locked/unlocked areas
- Progress bars
- Region lore
- Area quests

### 6. Activity history

Shows:

- Imported runs
- Distance
- Time
- Pace
- XP earned
- Rewards earned
- Optional route map

### 7. Quest log

Shows:

- Active quests
- Completed quests
- Weekly quests
- Rewards

### 8. Inventory

Shows:

- Chests
- Items
- Equipped cosmetics
- Titles

### 9. Skill tree

Shows:

- Available skill points
- Unlockable nodes
- Active bonuses

### 10. Settings

Shows:

- Strava connection status
- Disconnect Strava
- Privacy settings
- Delete account later

## Game processing pipeline

When a new activity is imported:

```text
1. Validate activity.
2. Normalize units.
3. Store raw activity snapshot.
4. Calculate XP.
5. Update user total stats.
6. Check quest progress.
7. Complete quests.
8. Grant rewards.
9. Add area progress.
10. Unlock areas if thresholds met.
11. Add notifications.
12. Return reward summary.
```

## Activity validation

MVP sanity checks:

- Activity type must be Run or compatible configured type.
- Distance must be greater than minimum threshold, e.g. 500m.
- Moving time must be greater than minimum threshold.
- Average pace must be within sane limits.
- Ignore duplicates by Strava activity ID.
- Flag suspicious activity but do not ban.

Suggested sanity checks:

```text
distance_km >= 0.5
moving_time_seconds >= 180
average_speed <= 8 m/s for normal running validation
```

Do not overdo anti-cheat at MVP.

## Cron jobs

Useful cron tasks:

```text
Every 15 min:
- Process pending Strava webhook events

Hourly:
- Refresh near-expiry tokens if needed
- Generate notifications

Daily:
- Generate daily quests
- Close expired quests
- Backup important data

Weekly:
- Generate weekly quests
- Reset weekly leaderboards later
```

On shared hosting, use cPanel Cron Jobs.

## Mapbox use

Use Mapbox for:

- Real route map display
- Stylish map previews
- Possibly fantasy-style map layer if custom tiles are added later

Use client-side public token only for map rendering.

Restrict token by domain:

```text
haydenn.co.za
www.haydenn.co.za
future Trailbound domain
```

## Environment variables

`.env` example:

```env
APP_ENV=local
APP_URL=https://haydenn.co.za
DB_HOST=localhost
DB_NAME=trailbound
DB_USER=trailbound_user
DB_PASS=change_me

STRAVA_CLIENT_ID=change_me
STRAVA_CLIENT_SECRET=change_me
STRAVA_VERIFY_TOKEN=random_webhook_verify_token
STRAVA_REDIRECT_URI=https://haydenn.co.za/api/strava/callback.php

MAPBOX_PUBLIC_TOKEN=pk.change_me

APP_KEY=random_32_plus_char_secret
```

## Security requirements

- Use HTTPS.
- Use password hashing with `password_hash`.
- Use PDO prepared statements.
- Use CSRF tokens for forms and state-changing requests.
- Use secure session cookie flags.
- Store API secrets server-side only.
- Validate all incoming API input.
- Rate limit login and sync endpoints.
- Do not expose raw Strava tokens.
- Allow users to disconnect Strava.
- Allow activity deletion later.

## Privacy requirements

Route data is sensitive.

MVP rules:

- Imported activities are private by default.
- Do not show exact route publicly.
- Do not publish start/end coordinates.
- Public-facing data should be totals only unless user opts in.
- Provide a privacy settings screen.
- User can disconnect Strava.
- User can request/delete their data later.

## Data retention

MVP:

- Store imported activities.
- Store route summary/polyline only if needed.
- Store raw Strava response in limited JSON field for debugging only during development.
- Avoid storing unnecessary personal data.

Production:

- Remove or minimize raw API snapshots.
- Keep only fields needed for app logic.
- Add delete/export tools.

## Admin panel

Not required for first MVP, but recommended soon.

Admin features:

- View users
- View imported activities
- View failed syncs
- View webhook events
- Create/edit quests
- Create/edit items
- Create/edit regions
- View suspicious activity flags
- Trigger manual sync for user

## MVP acceptance criteria

- User can register/login.
- User can connect Strava OAuth.
- User can manually sync activities.
- Imported runs are saved.
- XP is calculated.
- At least one quest can complete.
- Rewards are granted.
- User can open a chest.
- Area progress updates.
- Skill point can be spent.
- User can see dashboard/world/inventory/quest log.
