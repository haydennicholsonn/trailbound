# Trailbound Expansion Plan

## Phase 0: Stabilise The Live App

- Keep HTTPS healthy and make geolocation work through the real domain.
- Run database migrations before each deploy so new game systems do not break login.
- Keep server code mirrored locally and committed on `feature/trailbound-game-expansion-polish`.

## Phase 1: Product Feel And Trust

- Make Orrin feel alive: palette-aware depth, cursor/scroll tracking, friendly blink, click affection, and contextual speech after runs/posts.
- Make online presence obvious and reliable for the current user and friends.
- Fix profile media upload feedback and cache display for avatar and cover images.
- Make activity posts read like a premium social timeline with larger body text, clean reactions, comments, and share affordances.
- Keep WhatsApp-style chat conventions: clear chat rail, search, unread/favourite filters, message bubbles, and timestamps inside bubbles.

## Phase 2: Game Loop

- Expand quests into region questlines with lore, rewards, prerequisites, progress bars, and map markers.
- Add items as real rewards: inventory, rarity, equipped cosmetics, boosts, and collectible lore items.
- Add Tears wallet economy with shop purchases and skill unlocks.
- Add skill tree paths for Explorer, Tempo, Endurance, and Social play.
- Add daily, weekly, and monthly Trailbound challenges with streaks and leaderboard hooks.

## Phase 3: Social Game

- Merge friends and feed into a single Social hub with timeline, friends, challenges, and shareable quest/run moments.
- Add friend challenges: challenge a friend to a distance, region, streak, or questline.
- Add post attachments for quest badges, unlocked items, region claims, and run dashboards.
- Add social sharing text with `#trailboundapp` and a generated share card.

## Phase 4: Map And Runs

- Make locked Cape Town regions show distinct outlines, names, and fog instead of a single grey blob.
- Add full-screen map mode where the map becomes the app background and the HUD floats over it.
- Add a run map preview in Runs with route/marker history, pace pins, and unlocked-region context.
- Keep the map feeling alive with current user, friends, beacons, quests, and region state.

## Phase 5: Admin And Monetisation

- Turn Admin into an HQ board: health, players, runs, quests, economy, regions, packages, and moderation signals.
- Add filters by player segment, date range, region, package, quest status, and activity source.
- Keep package management admin-owned with Free now and paid tiers ready for payment integration later.
- Add package selection at signup and package management in Profile.

## Product Pass: Polish, Game Loop, Onboarding, Social UX, Mobile, Notifications, Map, Registration, And Infrastructure

Trailbound needs to feel like a real, premium product rather than a demo. The current best-looking screens set the minimum quality bar for every new feature, screen, modal, form, tooltip, empty state, admin view, and mobile layout.

### Core Outcomes

- Continue unfinished work from the existing phases without changing direction.
- Make the core loop obvious: sign up, detect starting shard from real location, choose a runner class, learn the world, log/sync runs, earn XP/Tears/items/badges, unlock map progress, complete quests/challenges, share achievements, and return for social updates.
- Improve onboarding, comprehension, mobile behaviour, location registration, notifications, realtime behaviour, profile, messages, feed, progress, admin, and deployment readiness.
- Add support notes for serving Trailbound from `trail.haydenn.co.za` while keeping `haydenn.co.za` free for the future personal showcase and future `projectname.haydenn.co.za` projects.

### Implementation Phases For This Pass

1. **Foundation and comprehension**
   - Preserve current app route/page after refresh.
   - Add Help/Wiki and Progress navigation.
   - Add first-login tutorial state, replay from Settings/Help, and concise onboarding copy.
   - Show clear "what should I do next?" guidance on Dashboard and Progress.

2. **Registration and identity**
   - Replace manual starting shard selection with GPS-driven shard detection.
   - Add mobile-safe geolocation permission copy, retry, loading, success, denied, unsupported, and insecure-context states.
   - Add password show/hide toggle and verbose validation feedback.
   - Present runner type as RPG class cards with playstyle, strengths, and skill-tree starting position.
   - Add optional referral/friend code entry, system-generated friend codes, copy/share actions, and admin referral stats.

3. **Game loop and rewards**
   - Improve quest completion, XP, Tears, unlock, and next-objective feedback.
   - Make Tears visible in sidebar, profile, shop, inventory, quests, challenges, wallet, and admin.
   - Expand Progress with level, XP, quest, badge, weekly goal, challenge, map, skill tree, Tears, and next objective sections.

4. **Map and mobile**
   - Show real-world place names next to shard names.
   - Make shard list entries clickable with a polished shard detail modal.
   - Keep undiscovered regions distinct with outlines/fog.
   - Make full-screen map mode work on mobile.
   - Add mobile menu scroll affordance and configurable menu-toggle side.

5. **Social, chat, notifications, and realtime**
   - Keep feed readable like an X timeline with post detail modals, larger text, reactions, comments, timestamps, and sticky composer.
   - Make comments/replies, reactions, notifications, friend requests, and messages update without refresh using websocket plus fallback polling.
   - Fix New Chat modal, friend search, conversation creation, optimistic sending, retry states, and WhatsApp-style timestamps inside bubbles.
   - Add notification preferences and friend muting.

6. **Profile, admin, infrastructure, and cleanup**
   - Add edit bio, reliable profile background upload, friend code, Tears, package, badges, class, and recent achievements to Profile.
   - Improve Admin as a control centre with referrals, packages, player packages, friend codes, Tears, wallet, notifications, quests, challenges, regions, social activity, and health.
   - Document `trail.haydenn.co.za`, future showcase routing, DNS, SSL, cookies, CORS, OAuth redirects, and environment settings.
   - Remove dead code and consolidate repeated UI patterns without breaking existing functionality.

### Current Chunk

- Add this pass to the planning document.
- Add backend fields for friend code, referral attribution, tutorial completion, and mobile menu preference.
- Improve registration UX with location-first shard detection, class cards, referral input, password visibility, and clearer validation.
- Add Help/Wiki and Progress pages.
- Persist active app page through refresh.
- Add replayable tutorial affordance and Settings accessibility control.
