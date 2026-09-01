# AUDIT-BASELINE.md — Jasapedia Repository Audit (Phase 0)

Audit date: 2026-08-31 · Auditor: Principal Engineering Team (autonomous)

## 1. Repository State Before Audit

| Item | Finding |
|---|---|
| Version control | **No git repository** |
| Application code | **None** — empty directory |
| Pre-existing files | `opencode.json` only (opencode agent config; wires `laravel-boost` MCP → `php artisan boost:mcp`) |
| Docs | None |
| Database | None |
| Tests / CI / Docker | None |

**Conclusion:** Greenfield project. No legacy code to classify (KEEP/REFACTOR/REPLACE/REMOVE not applicable). Blueprint §195 satisfied trivially.

## 2. Environment Audit (Host Machine — Laragon)

| Component | Version | Status |
|---|---|---|
| PHP | 8.3.30 (ZTS) | OK — required modules present: pdo_mysql, mbstring, openssl, gd, fileinfo, exif |
| Composer | 2.8.4 | OK |
| MySQL | 8.4.9 (Laragon) | Running (`mysqld is alive`), root@127.0.0.1 no password (local dev only) |
| Redis | 5.0.14.1 (Laragon) | Binary present at `D:\laragon\bin\redis\…`, **not running by default** → started manually for dev session (PONG confirmed) |
| Node / npm | 24.15.0 / 11.13.0 | OK |
| phpredis ext | **Not loaded** | → use **predis** client (`composer require predis/predis` ✓ v3.6) |

## 3. Scaffold Performed

- `composer create-project laravel/laravel .` → **Laravel Framework 13.29.0** (blueprint says "Laravel 13"; satisfied)
- `composer require predis/predis` (no phpredis ext on host)
- `composer require laravel/boost --dev` → v2.7 (restores `php artisan boost:mcp` used by `opencode.json`)
- `.env` configured: APP_NAME=Jasapedia, APP_LOCALE=id-ID, DB mysql `jasapedia` (created, utf8mb4_unicode_ci), CACHE_STORE=redis, QUEUE_CONNECTION=database (redis-ready), REDIS_CLIENT=predis
- `php artisan key:generate` done
- Baseline migrations run on MySQL 8.4.9: users, cache, jobs ✓
- Baseline test suite: **2 passed** ✓

## 4. Locked Architectural Decisions (see docs/ADR/)

| ADR | Decision |
|---|---|
| 001 | Modular monolith: domain code under `app/Domain/<Module>`, HTTP layer per persona (Customer / Partner / Admin / Api v1), Eloquent models grouped per domain |
| 002 | Double-entry immutable ledger, **integer minor units (IDR = whole rupiah, 0 decimals)** — never float |
| 004 | Payment abstraction `PaymentGatewayInterface` + **SandboxGateway** (no merchant credentials exist → hard STOP condition §1.3 respected) |
| 008 | Storage: local disk now; S3-compatible via `FILESYSTEM_DISK` switch |
| 009 | Authorization: `roles/permissions/role_permission/user_role` + Laravel Policies + permission middleware; nothing client-side |
| 010 | AI = optional provider interface; **never** autonomous fund release / refund / KYC / dispute decisions |
| Frontend | Blade + Tailwind + Alpine (customer web PWA, partner web, admin) inside the monolith; API-first for Flutter later |

## 5. Risk Register (initial)

| Risk | Mitigation |
|---|---|
| Redis not auto-started on host | Documented start procedure; drivers degrade to database/file |
| No payment merchant credentials | Sandbox/manual gateway only; production adapters are drop-in |
| Single-host Windows dev | Deployment docs target Linux/Docker for prod |
| Scope breadth (50 phases) | IMPLEMENTATION_STATUS.md + NEXT_ACTION.md continuity protocol (§192) |

## 6. Audit Verdict

**GO.** Proceed to Phase 1 (Blueprint) → Phase 2 (Foundation) without pause.
