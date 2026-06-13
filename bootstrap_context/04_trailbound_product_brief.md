# 04 — Project Trailbound Product Brief

## Working title

**Project Trailbound**

This is a codename. Final product name can change later.

Other possible names:

- Trailbound
- RuneRun
- Pathbound
- Runebound
- QuestMiles
- Wayfarer
- The First Road

Avoid locking the name too early.

## One-line pitch

A fantasy RPG running app where real-world runs unlock quests, XP, loot, areas, gear, and Path of Exile-style character progression.

## Core idea

You do the run in the real world using Strava.

Then you come back to the web app and your fantasy character progresses.

```text
Run in real life
Sync from Strava
Earn XP and rewards
Reveal new areas
Open chests
Spend skill points
Choose the next quest
```

The user does not need to keep a browser tab open while running.

This makes the first real version much more practical than trying to build live GPS tracking in a web app.

## Product goal

Make running feel like progression.

The app should make users think:

- “I want to run because I want to unlock the next area.”
- “One more run and I get a chest.”
- “This route counts toward my quest.”
- “My character is getting stronger because I am getting fitter.”
- “This is like an RPG layer on top of my real training.”

## Target user

Primary:

- Gamers who want to run more
- Runners who like RPG systems
- People motivated by progression, quests, and unlocks
- People who already use Strava or are willing to

Secondary:

- Friends testing the app
- Small invite-only community
- Future public users

## Platform strategy

### MVP

Web app using:

- PHP backend
- MySQL database
- Strava OAuth
- Strava activity import
- Mapbox map display
- Game progression layer

### Not MVP

- Native mobile app
- Live browser GPS tracking
- Garmin direct integration
- Real-money monetisation
- Public global launch
- Complex anti-cheat

## Why Strava-first is the right move

Using Strava first means:

- No need to build run recording from scratch
- User can run with any Strava-compatible device/app
- Phone lock/background tracking is handled by Strava/Garmin/watch apps
- Web app can focus on the fun part: progression, rewards, maps, quests
- Much better for cPanel/PHP hosting

## Garmin strategy

Garmin direct integration is a strong future feature, but should not block MVP.

Reason:

- Garmin Connect Developer Program/API access normally requires approval.
- It is better to launch with Strava and later add Garmin either directly or indirectly via Strava sync.

## Core game loop

```text
1. User connects Strava.
2. User completes a run.
3. Strava sends webhook or user manually syncs.
4. Backend imports new activity.
5. System validates run.
6. XP/rewards are calculated.
7. User opens Trailbound.
8. New rewards are shown.
9. World map reveals progress.
10. User spends points/unlocks nodes.
11. User chooses next quest.
12. User runs again.
```

## MVP user flow

### First visit

1. User lands on Trailbound app page.
2. Sees cinematic intro.
3. Clicks `Start Your Journey`.
4. Creates account or logs in.
5. Connects Strava.
6. Picks starter avatar/class.
7. Gets first quest: `Complete your first 2km run`.
8. App shows empty world map with first locked area.

### After first run

1. User runs using Strava.
2. User comes back to Trailbound.
3. Clicks `Sync Runs` if webhook not implemented yet.
4. New run is imported.
5. App displays reward sequence:
   - Quest complete
   - XP gained
   - Level up if applicable
   - Chest earned
   - Area discovered
6. User opens reward chest.
7. User equips cosmetic/item.
8. User sees next quest.

## Game fantasy

The user is an adventurer moving through a dark fantasy world.

Their physical runs power exploration.

Example:

```text
You ran 3.24km.
Your character travelled through The Ashwood Road.
You discovered: The Broken Shrine.
You found: Weathered Boots.
You earned: 320 XP.
```

## Theme

Fantasy RPG adventurer with Path of Exile-style progression.

Tone:

- Dark fantasy
- Not childish
- Not goofy fitness cartoon
- Mysterious
- Rewarding
- Slightly gritty
- Beautiful map visuals
- “One more run” energy

## MVP features

### Account

- Register
- Login
- Logout
- Password reset later
- User profile
- Strava connection status

### Strava integration

- OAuth connect
- Store access token and refresh token securely
- Import recent activities
- Manual sync button
- Optional webhook in phase 1.5

### Run import

- Import run distance
- Moving time
- Elapsed time
- Average speed/pace
- Start date
- Polyline/map summary if available
- Activity type
- Strava activity ID

### Progression

- XP
- Levels
- Streaks
- Skill points
- Quest completion
- Reward chests
- Basic inventory
- Area unlocks

### Map/world

- User sees a fantasy world map
- Areas start locked/fogged
- Runs unlock area progress
- Some areas require quest chains
- Mapbox can show real run routes separately from the fantasy map

### Quests

MVP quest types:

- Complete first run
- Run X km total
- Run X times this week
- Complete a run over X km
- Run faster than previous average pace
- Explore a new route
- Maintain a streak

### Rewards

MVP reward types:

- XP
- Coins
- Chest
- Cosmetic item
- Title
- Skill point
- Area unlock progress

### Avatar

MVP avatar is not a full animated character.

Use:

- Character card
- Class name
- Equipped item list
- Cosmetic portrait/icon
- Stats/progression display

## Future features

- Web push notifications
- Friend system
- Party quests
- Guilds
- Weekly boss events
- Seasonal map
- Rare cosmetics
- Deeper passive tree
- Route heatmaps
- Route suggestions
- Garmin direct integration
- Native companion app
- Public profiles
- Challenge invites
- AI-generated quest text
- Generated fantasy map regions
- Full animated avatar
- Team/clan raids
- Leaderboards

## Product risks

### Strava API limitations

Strava apps have rate limits and new apps start in a limited mode. Public access beyond personal use may require app review.

### Garmin API access

Garmin direct API integration is not guaranteed immediately because access generally requires approval.

### GPS privacy

Route data is sensitive. Route visibility should default to private.

### Motivation ethics

Avoid turning fitness into unhealthy gambling or punishment loops.

### Cheating

Users can fake activities. MVP should do sanity checks, not military-grade anti-cheat.

## Success criteria for MVP

The MVP is successful when:

- User can register/login.
- User can connect Strava.
- User can import at least one real run.
- The run gives XP.
- A quest completes.
- A reward chest can be opened.
- A new area unlocks.
- User can see progression.
- The experience feels like a game, not just a fitness dashboard.
