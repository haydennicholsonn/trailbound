# Trailbound

Trailbound turns real-world running into a living territory game.

Cape Town is split into playable regions. Runs reveal zones, quests unlock new objectives, friends appear live on the map, and every activity feeds into a character-style progression system with XP, badges, inventory, challenges, and a future-ready package model.

The product direction is simple: make fitness feel social, exploratory, and game-like without losing the honesty of real movement.

## Status

Trailbound is in active alpha.

The current build includes authentication, profile management, a Cape Town map, region unlocks, run logging, quests, social feed, direct messages, live websocket events, admin tooling, wallet/items/shop scaffolding, skill tree scaffolding, challenge scaffolding, and package management groundwork.

## Highlights

- Real Cape Town territory map with region unlock state and fog-of-war styling
- Run logging with XP rewards, recent history, and run dashboard modals
- Location logging so the app can unlock the correct area from the user's current position
- JWT-based session authentication with register/login flows
- Full profile suite with avatar, profile cover, bio, runner type, privacy, package, and badges
- Social feed with activity posts, comments, reactions, and run deep-links
- WhatsApp-inspired direct messaging UI backed by live websocket refreshes
- Friends, online presence, rally beacons, and friend map markers
- Quest system with biome context, rewards, progress, and modal detail views
- Wallet, Tears currency, inventory, item rewards, shop, badges, skill tree, and challenge foundations
- Admin HQ with player, run, region, quest, health, and package-management surfaces
- Dark/light theme support and selectable accent palettes
- Dockerized production stack with Caddy, PHP/Laravel, Redis, Postgres/PostGIS, workers, scheduler, and realtime service

## Tech Stack

| Layer | Technology |
| --- | --- |
| App framework | Laravel 13 |
| Frontend | React 19, Vite 8 |
| Maps | MapLibre GL |
| Icons | Lucide React |
| Database | PostgreSQL with PostGIS image |
| Cache/queue/realtime bus | Redis |
| Realtime gateway | Node websocket service |
| Web server/TLS | Caddy |
| Runtime | Docker Compose |

## Repository Layout

```text
.
|-- app/                    # Laravel models, controllers, support services
|-- config/                 # Laravel configuration
|-- database/               # Migrations, factories, seeders
|-- docs/                   # Product and expansion planning
|-- realtime/               # Websocket relay service
|-- resources/
|   |-- css/app.css         # Product UI, theme, and responsive styling
|   |-- js/app.jsx          # React application shell and feature surfaces
|   `-- views/              # Laravel Blade entry views
|-- routes/                 # Web/API routes
|-- compose.yml             # Production Docker Compose stack
|-- Dockerfile              # Multi-stage app image build
|-- Caddyfile               # Caddy routing and TLS config
|-- package.json            # Frontend dependencies and scripts
`-- composer.json           # Laravel dependencies and scripts
```

## Local Development

Install PHP, Composer, Node.js, and a PostgreSQL/Redis setup, or use the Docker stack as the source of truth.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run dev
php artisan serve
```

For the full Laravel dev loop:

```bash
composer run dev
```

Build production frontend assets:

```bash
npm run build
```

Run tests:

```bash
composer test
```

## Environment

The production stack expects a `.env` file beside `compose.yml`. Do not commit real secrets.

Core values used by the Docker stack:

```env
APP_NAME=Trailbound
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://your-domain.example

DOMAIN=your-domain.example
ACME_EMAIL=hello@example.com

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=trailbound
DB_USERNAME=trailbound
POSTGRES_PASSWORD=change-me

REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD_VALUE=change-me
```

Optional integrations:

```env
STRAVA_CLIENT_ID=
STRAVA_CLIENT_SECRET=
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
```

Google sign-in is intentionally parked until the production domain/OAuth redirect configuration is finalized.

## Production Deployment

The production image builds the frontend assets in a Node stage, installs Laravel dependencies in a Composer stage, and runs PHP from the final app image.

```bash
docker compose build app worker scheduler realtime
docker compose up -d
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan config:cache
```

Health check:

```bash
curl -fsS https://your-domain.example/api/health
```

Useful operational checks:

```bash
docker compose ps
docker compose logs --tail=100 app
docker compose logs --tail=100 realtime
docker stats --no-stream
```

## Game Systems

Trailbound is being built around one central loop:

1. Log a real run.
2. Resolve the run against the user's current or selected region.
3. Award XP, Tears, quest progress, and possible item/badge rewards.
4. Reveal map territory and unlock new objectives.
5. Share the moment socially through the feed, friends, messages, and future share cards.

Current and planned systems are tracked in [docs/trailbound-expansion-plan.md](docs/trailbound-expansion-plan.md).

## Admin

The admin dashboard is intended to become the operational HQ for the game. It already has surfaces for:

- App health
- Latest runners
- Player stats
- Region performance
- Quest completion
- Package management

Planned admin improvements include stronger filtering, economy views, moderation signals, package analytics, challenge controls, and region tuning.

## Security Notes

- Secrets belong in `.env`, never in Git.
- Production should only run behind HTTPS so browser location APIs work.
- Authentication is session/JWT based through the Laravel API.
- Profile media uploads are stored through Laravel storage and exposed via the public storage symlink.
- Payment code is not live yet; paid packages are blocked until a payment provider is integrated.

## Roadmap

- Full-screen map mode with floating HUD
- Runs tab mini-map with route and pace markers
- Richer questlines, lore, and region-specific objectives
- Equippable items, cosmetics, boosts, and inventory detail views
- Friend challenges and Trailbound daily/weekly/monthly challenges
- Social sharing cards with `#trailboundapp`
- Package upgrade/downgrade flows once payments are ready
- Admin HQ filters, charts, and economy controls
- Google OAuth once the domain and redirect URLs are final
- Code splitting for the large React bundle

## Contributing

This is a private alpha project. Keep changes focused, test the affected flow, and avoid committing generated assets, local databases, or environment files.

Recommended flow:

```bash
git checkout -b feature/short-description
npm run build
composer test
git commit -m "Describe the product change"
```

## License

Private project. All rights reserved.
