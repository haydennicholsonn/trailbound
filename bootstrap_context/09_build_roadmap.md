# 09 — Build Roadmap

## Build philosophy

Do not try to build everything at once.

Build the personal site and Trailbound in phases.

The first goal is:

> A polished personal site that links into a real MVP running app where one Strava run creates real game progress.

## Phase 0 — Setup

### Goals

- Prepare hosting
- Prepare local dev environment
- Create repo
- Decide folder structure
- Get API accounts ready

### Tasks

- Create Git repository.
- Create local project folder.
- Set up local PHP/MySQL environment.
- Confirm Afrihost PHP version.
- Confirm MySQL database access.
- Confirm cron support.
- Confirm whether Composer is available.
- Create Strava developer app.
- Create Mapbox account/token.
- Create `.env`.
- Set up HTTPS on haydenn.co.za.

### Done when

- Local dev runs.
- Database connects.
- `index.html` deploys to Afrihost.
- `.env` is not public.

## Phase 1 — Personal site MVP

### Goal

Launch haydenn.co.za as a polished one-page interactive showcase.

### Build

- Hero scene
- Boot animation
- Player profile section
- Skill/stat section
- Featured Trailbound section
- Final contact/follow section
- Responsive layout
- Reduced motion support

### Do not build yet

- Full Trailbound app
- Login
- Database admin
- Blog
- CV page

### Done when

- Site feels polished.
- Project focus is clear.
- Works on desktop and mobile.
- No employer/company references.
- No broken animation.
- Live on haydenn.co.za.

## Phase 2 — Trailbound app skeleton

### Goal

Build the app shell and account system.

### Build

- `/app/` route/page
- Register/login/logout
- Dashboard shell
- Onboarding flow
- Choose class
- Empty world map
- Empty quest log
- Empty inventory
- Empty skill tree
- Settings page

### Done when

- User can register and log in.
- User can choose class.
- Dashboard displays user.
- Navigation works.

## Phase 3 — Strava connection

### Goal

Connect Strava and import real runs.

### Build

- Strava developer app setup
- OAuth connect endpoint
- Callback endpoint
- Token storage
- Token refresh
- Manual sync
- Store imported runs
- Activity list screen

### Done when

- User connects Strava.
- User clicks Sync.
- At least one real Strava run imports.
- Duplicate sync does not duplicate the run.

## Phase 4 — Game engine MVP

### Goal

One real run creates real game progression.

### Build

- XP calculation
- Level curve
- First quest
- Quest progress
- Quest completion
- Chest reward
- Chest opening
- Item grant
- Area progress
- Area unlock
- Reward notification

### Done when

A user can:

- Import a run
- Complete `The First Road`
- Earn XP
- Open a chest
- Unlock `Ashwood Gate`
- See a reward animation

## Phase 5 — Polish pass

### Goal

Make it feel like a real game.

### Build

- Reward reveal animation
- Chest open animation
- Area discovered animation
- Skill tree node visuals
- Better dashboard UI
- Better fantasy copy/lore
- Loading states
- Empty states
- Error states
- Mobile pass

### Done when

- The app feels exciting after sync.
- Rewards are readable.
- No ugly debug screens.
- UI is consistent with personal site.

## Phase 6 — Strava webhooks

### Goal

Reduce manual syncing.

### Build

- Webhook verification
- Webhook event storage
- Cron processor
- Sync notification logic

### Done when

- New Strava activity events are received.
- App can process activity without manual sync after webhook event.
- Manual sync still exists as fallback.

## Phase 7 — Privacy + safety

### Goal

Make app safe enough for invite testing.

### Build

- Route visibility default private
- Hide exact route from public views
- Disconnect Strava
- Delete imported activity
- Basic anti-cheat flags
- Privacy page
- Terms/privacy draft

### Done when

- User can control data.
- No public exact route leaking.
- Suspicious runs get flagged, not rewarded blindly.

## Phase 8 — Invite beta

### Goal

Let a few friends use it.

### Build

- Invite codes or closed registration
- Basic admin panel
- Error logging
- Sync failure view
- Feedback link
- Friend-only leaderboard optional

### Done when

- 3–10 users can safely test it.
- Issues can be debugged.
- Feedback can be collected.

## Phase 9 — Future expansion

### Possible additions

- Garmin direct integration
- Native app
- Web push notifications
- Friends
- Guilds
- Party quests
- Weekly bosses
- Seasonal events
- Deeper passive tree
- Generated fantasy map
- Route recommendation
- Paid cosmetic support later, but no paid lootboxes

## Development order for AI coding

Use this order when prompting an AI coding agent:

1. Create static personal site structure.
2. Build core CSS design system.
3. Build scroll scene system.
4. Build boot animation.
5. Build project section.
6. Create PHP bootstrap/database.
7. Create users/auth tables.
8. Build auth endpoints.
9. Build app shell.
10. Add Strava OAuth.
11. Add activity import.
12. Add game engine functions.
13. Add UI reward flow.
14. Add polish.

## Quality bar

Do not move to the next phase if the current phase feels half-baked.

The personal site should not just “work”.
It must feel good.

Trailbound should not just “import a run”.
It must make the run feel rewarding.
