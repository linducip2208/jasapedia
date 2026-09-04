# Jasapedia

**"Every Service, One Platform."** — *"Semua Jasa, Satu Platform."*

🌍 Languages / Bahasa / اللغات: [Indonesia](README.id.md) | **English** | [العربية](README.ar.md)

A complete *service commerce* platform: a marketplace for home services, professional services, digital services, freelancers, field technicians, vendor companies, project-based work, RFQs, contracts, and corporate procurement — in a single ecosystem.

## Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13 / PHP 8.3 / MySQL 8 / Redis |
| Auth | Sanctum (API tokens) + TOTP 2FA for privileged accounts |
| Frontend | API-first (`/api/v1`, 223 routes) + Blade/Tailwind Web UI — ready for Flutter (customer & partner apps) |
| Realtime | Broadcast events (Reverb-compatible), DB as source of truth, polling fallback |
| Money | Integer IDR, double-entry immutable ledger, price snapshot per transaction |
| Media | Disk-agnostic `MediaService` (`MEDIA_DISK`: public/s3/r2), real MIME validation (finfo) + magic bytes + size cap |
| Admin | AdminLTE 4.9.1 (Bootstrap 5) as an isolated Vite entry for `/admin` only |

---

## Features

### 1. Customer Storefront (Web + API)

- **Super-app home** — hero, 21 categories, 6 entry points (Find Services / Book a Technician / Post a Request / Find a Freelancer / Create a Project / Business), popular services, how-it-works, trust section, provider & business CTAs
- **Explore & search** — `SearchProviderInterface` (SQL active, Meilisearch-ready via `SEARCH_DRIVER`), category/city/price/rating filters, filter chips + mobile drawer, sorting, suggest endpoint
- **Service detail 2.0** — media gallery, sticky purchase panel, schedule, emergency surcharge, warranty, verified reviews, packages & add-ons
- **Checkout & orders** — backend-authoritative pricing quote (frozen in a snapshot), slot selection, address snapshot locked at booking, emergency capable
- **Payments** — sandbox gateway on web, signed idempotent webhooks, amount-mismatch guard, double-pay guard; production adapters for **Xendit** & **Midtrans** via `PAYMENT_DRIVER`
- **Order tracking** — real-time status timeline, completion confirmation, OTP check-in (customer verifies the technician), cancellation governed by the state machine
- **Reviews** — 1 review/order (completed orders only), dimension ratings from category config, partner rating recomputed from aggregates
- **Favorites** — DB-unique toggle for services & providers, favorites page
- **Request for Quotation (RFQ)** — publishing wizard + photo attachments, deadline, visibility (public/invited), compare & accept quotations (versioned, immutable approval)
- **Project Marketplace** — publish projects, receive proposals, shortlist/award, two-party contracts, milestones: fund → work → revision → approve → **release + automatic ledger posting**
- **Chat** — per-order & direct conversations, idempotent (`client_message_id`), read receipts, attachments, polling fallback (broadcast-ready)
- **Notifications** — notification center, unread count, per-event×channel preferences, critical-event policy
- **Account center** — dashboard, orders by status, profile, multiple addresses (lat/lng), notifications
- **Blog & CMS** — blog posts, static pages (`/halaman/{slug}`), homepage CMS blocks

### 2. Partner / Provider Center (Web + API)

- **Onboarding & KYC** — partner registration (freelancer/individual/vendor company), KYC/KYB submission, documents, admin verification decisions + audit log
- **Vendor organizations** — partner_organizations + members with scoped RBAC (owner/manager/dispatcher/finance/PM/worker), skills, service areas (city/radius), payout destinations
- **Service management** — service CRUD + gallery upload (MIME-hardened), packages, add-ons, pricing per model (per_unit/hourly/daily/package/fixed), pause/activate
- **Availability** — weekly schedules, blocks/leave, **race-safe** slot engine (unique constraint + concurrency tests)
- **Order inbox** — accept/start/complete orders, transitions via the state machine
- **Dispatch** — job offers with transparent scoring (rating, distance, acceptance rate), auto-direct/broadcast/sequential/manual/vendor-internal modes, first-accept-wins, offer TTL
- **Field operations (API `/field/*`)** — on-the-way → arrived → OTP check-in → start work → before/after evidence → materials → **structured AdditionalChargeRequest** (24h TTL; chat text never changes amounts) → submit completion
- **RFQ & quotations** — browse open requests, send/revise quotations (versioned)
- **Projects & contracts** — submit/withdraw proposals, milestone start/submit, work logs
- **Finance** — balance, settlement history, withdrawals (race-safe reservation, minimum amount, double-completion blocked), payout account management
- **Reviews** — read + respond to reviews

### 3. Jasapedia Business (Corporate)

- **Corporate organizations** — branches, departments, cost centers, employees with roles & spend limits
- **Approval matrix** — manager + finance thresholds, require_category_approval, allowed categories
- **Budgets** — allocation per cost center per period with usage tracking
- **Service requests** — employee → request → two-level approval → converted to an order with the **PO reference preserved**
- **Corporate dashboard** — approvals, requests, spending summary

### 4. Admin Command Center (`/admin`)

- **AdminLTE 4.9.1 shell** (Bootstrap 5, `data-bs-theme` dark mode), isolated as a separate Vite entry (`resources/css/admin.css` + `resources/js/admin.js`) — Bootstrap never loads on the Tailwind storefront
- **Real-metric dashboard** — GMV, order volume, cancel/dispute rate, ledger commission, field operations, **ledger balance check**
- **Orders** — monitoring of all orders + immutable status history
- **Provider verification** — KYC/KYB lifecycle (approve/reject + notes + audit)
- **Finance** — withdrawal lifecycle (review/process/complete/fail), balance check
- **Disputes** — resolve with full/partial refund options → executed through the ledger
- **Users** — user management + audit log of sensitive actions
- Access gated by granular permissions `admin.*` / `audit.view` / `reports.view`

### 5. Finance & Ledger (domain core)

- **Double-entry ledger** — Σdebits = Σcredits tested on every posting + a global invariant; corrections only via *reversing entries* (append-only, money rows are never deleted)
- **Commission** — immutable snapshot (unique per order), rate from settings
- **Settlement** — gross − additional − commission = vendor_net; double-settle guard
- **Withdrawal** — per-partner serialization + reservation accounting, race-safe
- **Refund** — eligibility (≤ paid − refunded) + concurrency lock; dispute refunds executed in the ledger
- **Reconciliation** — detects payment/payout/ledger diffs
- All amounts are **integer IDR** — no float money

### 6. Trust & Safety

- **KYC/KYB** — submission, officer decisions, audit trail
- **Disputes** — opened → evidence → mediation → decision flow, ledger-backed refunds
- **Warranty claims** — window validated from category config
- **Review moderation** — review/message reports, published/hidden status
- **Chat safety** — contact-share warnings, message reports, user blocking

### 7. Growth

- **Recurring services** — weekly/monthly schedules, idempotent occurrence materialization
- **Promotions & vouchers** — percent/fixed types, max discount, min spend, usage/per-user limits, first-order-only, stackable, vendor share
- **Referrals** — deterministic codes, qualification, rewards
- **Membership** — plans (schema live; billing cycle to follow)

### 8. AI (advisory-only)

- `AiManager` + provider abstraction, graceful degradation without credentials
- Endpoints: find a service, build a brief from requirements, summarize conversations — AI suggestions never mutate transactional data

### 9. Platform & Infra

- **RBAC** — 24 roles, 70+ granular permissions, scoped org permissions, `permission:` middleware
- **Auth** — purpose-based registration, login, sessions (list/revoke), lockout, password reset, **TOTP 2FA RFC-6238** enforced for privileged accounts
- **Audit log** — actor, before/after, IP, user-agent for sensitive actions
- **Location** — Indonesia location tree (country→subdistrict), 15 provinces / 33 cities seeded, customer addresses with lat/lng
- **Search & Geo abstraction** — `SearchProviderInterface`, `GeoServiceInterface` (haversine + radius filter)
- **Media** — `MediaService` (real MIME + magic bytes + size cap), `service-image` component with a category-icon fallback
- **PWA** — manifest + service worker (static-only cache; API/admin network-only), installable
- **Health & observability** — `/api/v1/health` (DB+Redis) + `/up`, structured logs
- **Hardening** — security headers, rate limiters (api/auth/webhook), error envelope without internal leakage, CSRF, XSS escaping, ownership checks on every route
- **Scheduler** — order expiry, recurring materialization, TTL cleanup (ACR, offers)

### 10. Web Surface (route map)

| Area | Prefix | Contents |
|---|---|---|
| Customer | `/`, `/explore`, `/jasa/*`, `/checkout`, `/orders`, `/favorit` | storefront, service detail, checkout, tracking, favorites |
| Account | `/akun/*` | dashboard, profile, notifications, addresses |
| Requests (RFQ) | `/kebutuhan/*` | wizard, quotations, accept quotation |
| Projects | `/proyek/*` | publishing, proposals, contract + milestones |
| Chat | `/chat/*` | conversation list, room, attachments |
| Provider | `/penyedia/{slug}` | public profile + transparent levels (New→Verified→Preferred→Top→Pro from real metrics) |
| Partner Center | `/partner/*` | KPI dashboard, services, orders, requests, projects, finance, reviews, onboarding + KYC |
| Business | `/business/*` | landing + corporate dashboard |
| Admin | `/admin/*` | command center (AdminLTE 4, separate dark layout) |
| Content | `/blog`, `/halaman/*` | blog + CMS pages |

Error pages 403/404/500 are localized in Indonesian; original `<x-brand.*>` branding (no Laravel branding).

### 11. API Surface (`/api/v1` — 150 endpoints)

`auth` (register/login/2FA/sessions/password) · `catalog` (categories/services/locations, partner service CRUD) · `addresses` · `orders` (quote/store/cancel/confirm/checkin/ACR decide) · `field` (offers, accept/reject, on-the-way/arrived/checkin/start-work/evidence/materials/ACR/submit) · `chat` (direct, per-order, messages, read, report) · `notifications` (+preferences) · `projects` (proposal decide/contract/milestones fund-approve-revision-release) · `partner/deals` (projects, proposals, contracts, milestones, worklogs) · `rfqs` + `quotations` · `reviews` + `disputes` · `corporate` (orgs, employees, policy, requests, approve, convert) · `ai` · `cms`/`blog`/SEO landing · `support` tickets · `partner` (profile, verification, skills, documents, service areas, payout, members) · `payments` · `health`

**API-first** design: the entire domain is consumable by Flutter/mobile with no duplicated logic.

---

## Demo Data

Jasapedia ships a **production-quality demo dataset** so the storefront, partner center, admin command center, project marketplace, RFQ, and Jasapedia Business feel like a living marketplace — not an empty install.

```bash
# Full demo dataset (defaults):
#   10,000 active service listings | 2,500 providers | 5,000 customers
#   3,000 orders | 500 projects | 500 RFQs | 7,000 reviews | 50 corporates
php artisan jasapedia:seed-demo

# Delete ONLY is_demo-tagged rows, then reseed from scratch:
php artisan jasapedia:seed-demo --fresh-demo

# Custom volume (for CI / low-end laptops):
php artisan jasapedia:seed-demo --services=210 --providers=21 --customers=40 --orders=25 --reviews=15 --fresh-demo
```

> ⚠️ **Never run in production.** The command **refuses to run** when `APP_ENV=production` unless given `--force` **and** an interactive confirmation. The dataset is tagged `is_demo=1` across all main tables — `--fresh-demo` deletes only demo rows; real production/customer data is never touched. Running the command a second time without `--fresh-demo` is **rejected** (idempotency guard).

**Invariants satisfied by the demo dataset:**

- Exactly **10,000 active service listings** distributed across **21 blueprint categories** (largest-remainder normalization, asserted by the seeder)
- Indonesian titles/descriptions/prices **specific per category** from a dictionary (no lorem ipsum), unique slugs
- Integer IDR prices in realistic per-category ranges (e.g. Cleaning Rp50k–2.5M; Renovation Rp500k–250M; Construction up to Rp1B)
- Providers: 60% individual/freelancer, 30% vendor, 10% company (partner_organizations + members), 75% verified; **level badges computed from data** (completed_jobs + rating) following existing logic — no fake badges
- Realistic locations concentrated on Jabodetabek, Bandung, Surabaya, etc. (coordinates = jittered valid city centers from LocationSeeder)
- Orders: a finance-complete subset runs **through the real domain services** (`OrderService` → `PaymentService` sandbox webhook → `OrderStateMachine` → `SettlementService` → `RefundService`/`WithdrawalService`) — **the double-entry ledger stays balanced**, Σ debits = Σ credits
- Reviews: only for `completed|settled|closed` orders (1 review/order), 5★ ~70% / 4★ ~21% / 3★ ~6%, dimension ratings follow `Category.config.review_dimensions`, partner ratings recomputed from aggregates (never written manually)
- Project marketplace + proposals + contracts + milestones; RFQ + quotations (open/closed/awarded); corporates (branches, departments, cost centers, approval policies, CSR + PO)
- Media: a deterministic local image pool per category (360 files: WebP 1200×800 covers, SVG provider avatars, category banners — no network downloads, no external hotlinks); 100% of services have a cover, 70% have 2+ gallery images, every provider has an avatar
- Homepage/explore/admin are fully populated out of the box: categories, popular services, verified providers, reviews, blog, SEO metadata
- All inserts are batched in **chunks of 500** (never 10,000 records over HTTP), deterministic seeding (`mt_srand`), media resolved via `MediaService::url()`

**Demo accounts (local/demo only):**

| Role | Email | Password |
|---|---|---|
| Customer | `customer@jasapedia.test` | `password` |
| Provider | `provider@jasapedia.test` | `password` |
| Company (vendor) | `company@jasapedia.test` | `password` |
| Corporate | `corporate@jasapedia.test` | `password` |
| Admin | `admin@jasapedia.test` | `password` (InitialAdminSeeder) |

Demo credentials are **never printed** in command output when `APP_ENV=production`.

Related env (`config/demo.php`): `DEMO_DATA_ENABLED=false` (master switch for auto-seed via `db:seed`), `DEMO_SEED=20260901`, `DEMO_SERVICES=10000`, `DEMO_EMAIL_DOMAIN=example.test` (all demo emails use a non-routable domain).

---

## Testing

```bash
php artisan test
npm run build   # frontend (Vite)
```

168 tests / 5,900+ assertions covering: unit (Money/TOTP/pricing/availability/finance invariants), feature (auth/RBAC/partner/catalog/order/payment/chat/notif), the **demo dataset** (media integrity, service distribution, financial invariants), and critical **E2E** flows:

- **Home Service (§126)**: search → book → pay → dispatch → accept → OTP check-in → evidence → additional charge → complete ✓
- **Project (§127)**: post → proposal → shortlist → award → contract → milestone funding → revision → approval → **release + ledger** ✓
- **Corporate**: request → two-level approval → order conversion (PO ref preserved) ✓
- **Dispute**: settled order → dispute → evidence → officer → full refund → ledger balanced ✓
- **Financial invariants (§54/§128)**: balanced ledger, double-settle/refund/withdrawal blocked, refund ≤ paid, webhook dedup ✓

---

## Setup (Laragon / local)

```bash
composer install
cp .env.example .env   # configure your DB
php artisan key:generate
php artisan migrate --seed
npm install && npm run build
php artisan serve
```

Redis (optional for dev): `D:\laragon\bin\redis\...\redis-server.exe --port 6379`

Admin seed: `admin@jasapedia.test / password` — **change it in production**.

## Status & Known Gaps

Live details: `IMPLEMENTATION_STATUS.md` + `FINAL_AUDIT.md`. Open gaps: membership billing cycle, custom offers in chat, Reverb realtime (polling fallback active), Meilisearch activation, quotation → service order conversion, browser E2E (Playwright), programmatic SEO Blade landing pages.

## Documentation

`docs/` — master PRD, architecture, domain model, state machines (order/project), RBAC matrix, chat spec, payment & ledger spec, QA plan, ADRs (001–005). Live implementation status: `IMPLEMENTATION_STATUS.md`.

## License

Proprietary — © 2026 Jasapedia.
