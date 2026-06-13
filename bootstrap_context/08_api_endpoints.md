# 08 — API Endpoints

## API style

Use JSON API endpoints under:

```text
/api/
```

All responses should use a consistent structure:

```json
{
  "success": true,
  "data": {},
  "message": "Optional message"
}
```

Errors:

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Human readable message"
  }
}
```

## Auth endpoints

### POST `/api/auth/register.php`

Creates account.

Request:

```json
{
  "email": "user@example.com",
  "password": "secret",
  "display_name": "Hayden"
}
```

Response:

```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "display_name": "Hayden",
      "level": 1
    }
  }
}
```

Validation:

- Email required
- Password min length 10 recommended
- Display name required
- Email unique

### POST `/api/auth/login.php`

Request:

```json
{
  "email": "user@example.com",
  "password": "secret"
}
```

Response:

```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "display_name": "Hayden"
    }
  }
}
```

### POST `/api/auth/logout.php`

Destroys session.

### GET `/api/auth/me.php`

Returns current logged-in user.

## Onboarding endpoints

### POST `/api/onboarding/choose-class.php`

Request:

```json
{
  "avatar_class": "wayfarer"
}
```

Allowed values:

- wayfarer
- scout
- warden
- strider
- stormrunner

### GET `/api/onboarding/status.php`

Returns whether user has:

- account
- class selected
- Strava connected
- starter quest assigned

## Strava endpoints

### GET `/api/strava/connect.php`

Redirects user to Strava OAuth.

Backend builds authorisation URL with:

- client_id
- redirect_uri
- response_type=code
- approval_prompt=auto
- scope=read,activity:read_all or relevant current Strava scope

Also include CSRF `state`.

### GET `/api/strava/callback.php`

Handles Strava OAuth redirect.

Query params:

- code
- state
- error

Responsibilities:

- Validate state
- Exchange code for token
- Store athlete and tokens
- Redirect user back to `/app/`

### POST `/api/strava/sync.php`

Manual sync.

Responsibilities:

- Verify login
- Load Strava connection
- Refresh token if needed
- Fetch recent activities
- Import new runs
- Process game rewards
- Return reward summary

Response:

```json
{
  "success": true,
  "data": {
    "imported_count": 1,
    "ignored_count": 0,
    "activities": [
      {
        "id": 123,
        "distance_km": 3.24,
        "xp_awarded": 598,
        "quests_completed": [
          "The First Road"
        ],
        "rewards": [
          "Old Travel Chest"
        ],
        "regions_unlocked": [
          "Ashwood Gate"
        ]
      }
    ]
  }
}
```

### GET `/api/strava/status.php`

Returns:

```json
{
  "success": true,
  "data": {
    "connected": true,
    "athlete_id": 123456,
    "last_sync_at": "2026-06-12 10:00:00"
  }
}
```

### POST `/api/strava/disconnect.php`

Disconnects Strava.

Should:

- Mark connection inactive
- Remove stored tokens if possible
- Keep imported activity data unless user chooses to delete it

### GET/POST `/api/strava/webhook.php`

Strava webhook.

GET:

- Handles webhook subscription verification challenge

POST:

- Stores webhook events
- Does not do heavy processing inline
- Returns quickly

## Dashboard endpoints

### GET `/api/game/dashboard.php`

Returns everything needed for main dashboard:

```json
{
  "success": true,
  "data": {
    "user": {},
    "stats": {},
    "level": {},
    "active_quests": [],
    "latest_activity": {},
    "pending_rewards": [],
    "current_region": {},
    "notifications": []
  }
}
```

## Activity endpoints

### GET `/api/activities/list.php`

Query params:

- page
- limit
- from
- to

Returns paginated imported runs.

### GET `/api/activities/detail.php?id=123`

Returns activity detail.

Should respect privacy.

### DELETE `/api/activities/delete.php?id=123`

Future.

Deletes or hides imported activity.

## Quest endpoints

### GET `/api/quests/active.php`

Returns active quests for user.

### GET `/api/quests/completed.php`

Returns completed quests.

### POST `/api/quests/claim.php`

If rewards are not auto-claimed.

Request:

```json
{
  "user_quest_id": 55
}
```

MVP can auto-claim quest rewards to reduce complexity.

## Rewards endpoints

### GET `/api/rewards/pending.php`

Returns reward events not yet shown in UI.

### POST `/api/rewards/mark-seen.php`

Marks reward notifications as seen.

### POST `/api/rewards/open-chest.php`

Request:

```json
{
  "user_chest_id": 12
}
```

Response:

```json
{
  "success": true,
  "data": {
    "item": {
      "name": "Ashwood Cloak",
      "rarity": "magic",
      "type": "cloak"
    }
  }
}
```

## Inventory endpoints

### GET `/api/inventory/list.php`

Returns:

- Chests
- Items
- Equipped items
- Titles

### POST `/api/inventory/equip.php`

Request:

```json
{
  "user_item_id": 99
}
```

Rules:

- Only one equipped item per item type, unless item type allows multiples.

## Skill tree endpoints

### GET `/api/skill-tree.php`

Returns:

- All active nodes
- User unlocked nodes
- Available skill points
- Prerequisites

### POST `/api/skill-tree/unlock.php`

Request:

```json
{
  "node_id": 4
}
```

Validation:

- User has enough skill points
- Prerequisites unlocked
- Node not already unlocked

## World map endpoints

### GET `/api/world/regions.php`

Returns all regions and user progress.

### GET `/api/world/current.php`

Returns current region and next unlock.

## Notification endpoints

### GET `/api/notifications/list.php`

Returns notifications.

### POST `/api/notifications/read.php`

Request:

```json
{
  "notification_id": 123
}
```

## Admin endpoints

Not MVP unless needed.

Recommended later:

```text
/api/admin/users.php
/api/admin/activities.php
/api/admin/quests.php
/api/admin/items.php
/api/admin/regions.php
/api/admin/sync-log.php
```

Protect with admin role.

## API implementation rules

- Every endpoint includes auth bootstrap.
- Every state-changing request validates CSRF token.
- Every DB call uses PDO prepared statements.
- Every endpoint returns JSON except OAuth redirects.
- Do not echo PHP warnings to users.
- Log server errors privately.
- Keep response payloads small.
