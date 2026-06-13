# 12 — Initial Build Task List

Use this as the first actual development checklist.

## Sprint 1: Personal site static MVP

### Task 1: Create file structure

```text
public_html/
  index.html
  assets/css/main.css
  assets/js/main.js
  assets/js/effects.js
  assets/img/logo.svg
  assets/img/avatar.svg
```

### Task 2: Build HTML sections

- Hero
- Player profile
- Skills
- Trailbound project
- Trailbound deep dive
- Contact/final scene

### Task 3: Build CSS design system

- Dark background
- Typography
- Panels
- Buttons
- HUD labels
- Responsive layout

### Task 4: Build scroll scene detection

- IntersectionObserver
- Active scene indicator
- Section reveal classes

### Task 5: Build animations

- Boot text
- Button shatter
- Light cursor trail
- Project card reveal
- Skill nodes reveal

### Task 6: Mobile polish

- Disable cursor trail
- Simplify HUD
- Fix spacing
- Test tap states

## Sprint 2: Trailbound setup

### Task 1: Create database

- Create MySQL DB
- Run schema
- Insert seed data

### Task 2: PHP bootstrap

- `.env`
- Database connection
- Response helper
- Auth/session helper
- CSRF helper

### Task 3: Auth

- Register
- Login
- Logout
- Current user

### Task 4: App shell

- `/app/index.html`
- Dashboard placeholder
- Quest placeholder
- World map placeholder
- Inventory placeholder
- Skill tree placeholder

## Sprint 3: Strava import

### Task 1: Strava OAuth

- Connect endpoint
- Callback endpoint
- Token exchange
- Token storage

### Task 2: Manual sync

- Refresh token
- Fetch activities
- Filter runs
- Insert new activities

### Task 3: Game process

- XP
- Quest progress
- Reward chest
- Area progress
- Notification

## Sprint 4: Game polish

- Reward modal
- Chest open animation
- Area unlock animation
- Character card
- Skill tree node unlock
- Activity detail screen

## First definition of done

The first real demo is done when:

1. haydenn.co.za looks sick.
2. Trailbound app lets you log in.
3. You can connect Strava.
4. You can import a run.
5. The run completes a quest.
6. You get a chest.
7. You unlock a fantasy area.
8. It feels like the start of a real game.
