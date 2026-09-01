# Jasapedia

**"Semua Jasa, Satu Platform."**

Platform *service commerce* lengkap: marketplace jasa rumah tangga, jasa profesional, jasa digital, freelancer, teknisi lapangan, perusahaan vendor, project-based work, RFQ, kontrak, dan pengadaan korporat — dalam satu ekosistem.

## Stack

| Layer | Teknologi |
|---|---|
| Backend | Laravel 13 / PHP 8.3 / MySQL 8 / Redis |
| Auth | Sanctum (API tokens) + TOTP 2FA untuk akun privileged |
| Frontend | API-first (`/api/v1`) — siap untuk Flutter (customer & partner app) |
| Realtime | Broadcast events (Reverb-compatible), DB sebagai source of truth, fallback polling |
| Money | Integer IDR, double-entry immutable ledger, snapshot harga per transaksi |

## Modul Implementasi

- **Identity & RBAC** — 24 role, 70+ granular permission, scoped org permissions, 2FA (TOTP RFC-6238), lockout, session management
- **Partner & Organization** — freelancer/individual/vendor company, member roles (owner/manager/dispatcher/finance/PM/worker), skills, dokumen, service area, payout destination
- **Catalog** — 21 kategori blueprint, service templates, fulfillment engine config-driven (11 tipe), packages, addons, warranty/cancellation/review dims per kategori
- **Location** — location tree Indonesia, customer addresses, address privacy (snapshot terkunci saat booking)
- **Availability** — jadwal mingguan, blocks/leave, slot engine dengan **race-safe booking** (unique constraint + concurrency test)
- **Pricing** — backend-authoritative calculator (per_unit/hourly/daily/package/fixed), addon, emergency surcharge, **frozen pricing snapshot**
- **Order** — state machine ketat (doc 10), immutable history, cancel/expire, slot release
- **Payment** — `PaymentGatewayInterface` + SandboxGateway, webhook signed + idempotent, amount-mismatch guard, double-pay guard
- **Dispatch** — scoring transparan, auto-direct/broadcast/sequential/manual/vendor-internal, first-accept-wins, offer TTL
- **Field Service** — OTP check-in, before/after evidence, materials, **AdditionalChargeRequest terstruktur** (chat text tidak pernah mengubah nominal)
- **Chat** — idempotent (client_message_id), read receipts, structured cards, contact-share warnings
- **Notifications** — in-app, preferences per event×channel, critical-event policy
- **Project Deal Flow** — Project → Proposal → Award → **Contract versioned** → **Milestone funding/submission/revision/approval/release** → ledger settlement otomatis
- **Finance** — Ledger double-entry (Σdebit=Σcredit diuji), Commission snapshot immutable, Settlement (double-settle guard), Withdrawal (reservasi race-safe, min amount), Refund (eligibility + lock konkuren) — **semua invariant test hijau**
- **Health & Observability** — `/api/v1/health` (DB+Redis), structured logs, audit log untuk aksi sensitif

## Testing

```bash
php artisan test
```

76 test / 380+ assertion meliputi: unit (Money/TOTP/pricing/availability), feature (auth/RBAC/partner/catalog/order/payment/chat/notif), **E2E kritis**:

- **Home Service (§126)**: search → book → pay → dispatch → accept → OTP check-in → evidence → additional charge → complete ✓
- **Project (§127)**: post → proposal → shortlist → award → contract → milestone funding → revision → approval → **release + ledger** ✓
- **Invariants finansial (§54/§128)**: ledger balanced, double-settle/refund/withdrawal blocked, refund ≤ paid, dedup webhook ✓

## Setup (Laragon / lokal)

```bash
composer install
cp .env.example .env   # sesuaikan DB
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Redis (opsional untuk dev): `D:\laragon\bin\redis\...\redis-server.exe --port 6379`

Seed admin: `admin@jasapedia.test / password` — **ganti di production**.

## Dokumentasi

`docs/` — Master PRD, arsitektur, domain model, state machine (order/project), RBAC matrix, chat spec, payment & ledger spec, QA plan, ADR (001–005). Status implementasi live: `IMPLEMENTATION_STATUS.md`.

## Lisensi

Proprietary — © 2026 Jasapedia.
