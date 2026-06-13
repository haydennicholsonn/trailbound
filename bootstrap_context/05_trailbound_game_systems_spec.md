# 05 — Trailbound Game Systems Spec

## Design target

Trailbound must make running feel like an RPG.

It should not be “Strava with badges”.

The game systems should create anticipation, reward, progression, choice, and identity.

## Core loop

```text
Plan quest
Run in real life
Sync activity
Complete objectives
Earn XP/rewards
Unlock area/story/item
Spend skill points
Choose next path
Repeat
```

## Player identity

The user is an adventurer.

MVP class options:

1. **Wayfarer**
   - Balanced progression
   - Default starter class

2. **Scout**
   - Bonuses for route variety and exploration

3. **Warden**
   - Bonuses for consistency and streaks

4. **Strider**
   - Bonuses for distance milestones

5. **Stormrunner**
   - Bonuses for speed/pace challenges

These can be cosmetic/flavour at MVP, then affect quest weighting later.

## XP system

### Base XP

Recommended MVP formula:

```text
base_xp = distance_km * 100
time_bonus = min(50, moving_minutes * 1)
quest_bonus = quest reward amount
streak_bonus = small percentage bonus
total_xp = base_xp + time_bonus + quest_bonus + streak_bonus
```

Example:

```text
3.2km run = 320 XP
28 moving minutes = 28 XP
Quest complete = 250 XP
Total = 598 XP
```

### Level curve

Use a simple curve:

```text
xp_required_for_next_level = 500 + (level * level * 75)
```

Early levels should come quickly.

Approximate targets:

```text
Level 1 -> 2: easy after first run
Level 5: a few runs
Level 10: several weeks for casual user
Level 20+: long-term progression
```

## Skill points

Award:

- 1 skill point per level
- Bonus skill point for major milestones
- Bonus skill point for region boss quests

## Skill tree

Inspired by Path of Exile, but much simpler for MVP.

### Tree structure

MVP tree:

- 3 main branches
- 10–15 nodes total
- Mostly passive bonuses
- No complex build-breaking systems yet

Branches:

### 1. Endurance Path

For users who run consistently or longer distances.

Example nodes:

- `Steady Feet`: +5% XP from runs over 3km
- `Long Road`: +10% area progress from runs over 5km
- `Iron Routine`: +1 streak protection per month

### 2. Explorer Path

For route variety and discovering new places.

Example nodes:

- `Cartographer`: +10% discovery progress on new routes
- `Hidden Trails`: extra chance to find exploration chests
- `Pathfinder`: new route quests appear more often

### 3. Tempo Path

For speed/pace improvement.

Example nodes:

- `Quickstep`: bonus XP when beating previous average pace
- `Momentum`: extra quest progress from negative splits later
- `Storm Pulse`: weekly pace challenge rewards improved

## Skill tree rules

MVP:

- Nodes have prerequisites.
- User spends skill points to unlock nodes.
- Nodes provide passive modifiers.
- Refund/reset is admin-only or not available at first.

Phase 2:

- Add respec item.
- Add keystone nodes.
- Add class-specific starting positions.
- Add visual tree like PoE but simplified.

## Quests

### Quest categories

1. **Main Quest**
   - Drives world unlocks
   - Example: `Reach the Broken Shrine`

2. **Daily Quest**
   - Small repeatable objective
   - Example: `Complete any 2km run`

3. **Weekly Quest**
   - Larger goal
   - Example: `Run 10km this week`

4. **Exploration Quest**
   - Rewards route variety
   - Example: `Run somewhere you have not run before`

5. **Challenge Quest**
   - Pace/distance improvement
   - Example: `Beat your previous 3km pace`

6. **Boss Quest**
   - Big milestone
   - Example: `Run 21km total this month`

### MVP quest table fields

Each quest should have:

- ID
- Name
- Description
- Type
- Objective metric
- Target value
- Reward XP
- Reward chest type
- Required area
- Active date range
- Repeatable flag

## Quest examples

### Starter quest

```text
Name: The First Road
Objective: Complete one run of at least 2km
Reward: 300 XP, Old Travel Chest, unlock Ashwood Gate
```

### Weekly quest

```text
Name: Footsteps Through Fog
Objective: Complete 3 runs this week
Reward: 600 XP, 1 skill point fragment, Scout Chest
```

### Distance quest

```text
Name: Beyond the Watchfire
Objective: Run 10km total
Reward: Unlock The Broken Shrine
```

### Pace quest

```text
Name: Outrun the Howl
Objective: Beat your average pace on a run over 2km
Reward: 400 XP, Stormrunner title progress
```

## Reward chests

Use the word **chests** instead of lootboxes in the UI to avoid gambling vibes.

MVP chest types:

- Old Travel Chest
- Scout Chest
- Warden Chest
- Shrine Chest
- Rare Relic Chest

## Item rarity

Use RPG rarity.

```text
Common
Magic
Rare
Epic
Legendary
```

MVP should mostly drop Common/Magic/Rare.

Legendary should be extremely rare or reserved for milestones.

## Reward philosophy

Avoid predatory design.

Rules:

- No paid lootboxes in MVP.
- No real-money chest buying.
- Chests are earned through activity.
- Rewards should feel fun, not gambling-driven.
- Important progression should not rely purely on random chance.

## Item categories

MVP:

- Head cosmetic
- Cloak/cape
- Boots
- Weapon cosmetic
- Aura/trail
- Title
- Badge
- Portrait frame

Phase 2:

- Pets
- Mounts
- Seasonal cosmetics
- Guild banners
- Map skins

## Example items

```text
Weathered Boots — Common
Ashwood Cloak — Magic
Lantern of the First Road — Rare
Stormlit Hood — Epic
Crown of the Long Road — Legendary
```

## Avatar stats

MVP visible stats:

- Level
- XP
- Total distance
- Runs completed
- Current streak
- Areas discovered
- Chests opened
- Titles earned

Avoid fake combat stats unless they tie to the game world.

Phase 2 fantasy stats:

- Endurance
- Discovery
- Momentum
- Resolve
- Luck

## Area unlock system

The fantasy world is split into regions.

MVP regions:

1. **The First Road**
2. **Ashwood Gate**
3. **The Broken Shrine**
4. **Mistfen Crossing**
5. **The Ember Hills**

Each region has:

- Unlock requirement
- Progress meter
- Quests
- Reward pool
- Visual map tile
- Lore snippet

Example:

```text
Ashwood Gate
Unlock: Complete The First Road quest
Progress: 0/10km
Reward: Scout Chest
Lore: The road bends beneath old trees. Something watches from the fog.
```

## World progress

Each imported run contributes to area progress.

```text
area_progress += distance_km * modifiers
```

When progress reaches the target:

- Area is discovered
- Reward chest granted
- New quest chain unlocked
- Map tile animates from fog to visible

## Streaks

Streaks should be motivating but not punishing.

MVP:

- Weekly streak: user ran at least X times this week
- Not daily streak only, because daily running can encourage overtraining

Recommended streak types:

- Weekly consistency streak
- Monthly distance goal streak
- Quest streak

## Notifications

MVP:

- In-app notifications only

Phase 2:

- Web push notifications

Notification examples:

```text
Quest complete: The First Road
New area discovered: Ashwood Gate
Chest earned: Old Travel Chest
Weekly quest expires in 24h
```

## Anti-burnout design

Do not punish missed days too harshly.

Include:

- Rest day recognition
- Weekly goals instead of only daily goals
- Streak protection
- Walking/hiking support later if desired
- No shame language

## Leaderboards

Not MVP unless easy.

Phase 2:

- Friends-only leaderboard first
- Weekly XP leaderboard
- Weekly quest completion leaderboard
- No public route leaderboard by default

## Privacy-first defaults

- Route visibility private by default.
- Public profile only shows safe summary stats.
- Do not show exact start/end points publicly.
- Allow deleting imported activities.
- Allow disconnecting Strava.
