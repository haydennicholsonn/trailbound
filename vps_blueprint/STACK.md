# Trailbound VPS Stack

Target server:

- Debian 13 amd64
- 1 vCPU
- ~1 GB RAM
- `/srv/codex`

## Decision

Use a lean Docker Compose deployment, not a hosted panel.

Services:

- `app`: Laravel 13 + PHP 8.3/8.4 + Inertia + React + TypeScript + Vite
- `web`: Caddy reverse proxy with automatic HTTPS
- `postgres`: PostgreSQL 18 with PostGIS
- `redis`: Redis for queues, cache, rate limiting, sync locks
- `worker`: Laravel queue worker
- `scheduler`: Laravel scheduler loop

## Why

Laravel gives the fastest path to a serious product:

- auth
- CSRF/session handling
- Strava OAuth
- queues and scheduler
- migrations
- validation
- notifications
- admin tooling later

React/TypeScript gives the UI room to become the cinematic game interface:

- HUD state
- canvas/WebGL effects
- map interactions
- reward animation sequences
- future Three.js/Mapbox views

PostgreSQL + PostGIS is a better long-term fit than MySQL for route/map data.

## VPS constraints

This server is small, so avoid:

- Coolify
- Kubernetes
- multiple Node SSR processes
- heavyweight observability stacks

Tune for:

- one app container
- one queue worker
- conservative Postgres memory
- Redis with maxmemory
- Caddy as the only public edge

## Codex CLI

Do not install Codex CLI on the VPS by default.

Reason:

- it would require OpenAI credentials on the server
- the server should run the app, not become the development workstation
- this local Codex session can provision over SSH and deploy from source

Install CLI later only if there is a clear maintenance workflow that needs it.
