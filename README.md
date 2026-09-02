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

## Demo Data

Jasapedia menyertakan **production-quality demo dataset** agar storefront, partner center, admin command center, project marketplace, RFQ, dan Jasapedia Business terasa seperti marketplace yang hidup — bukan instalasi kosong.

```bash
# Seed dataset demo lengkap (default):
#   10.000 service listing aktif | 2.500 provider | 5.000 customer
#   3.000 order | 500 project | 500 RFQ | 7.000 review | 50 corporate
php artisan jasapedia:seed-demo

# Hapus HANYA data bertanda is_demo, lalu seed ulang dari nol:
php artisan jasapedia:seed-demo --fresh-demo

# Custom volume (untuk CI / laptop lemot):
php artisan jasapedia:seed-demo --services=210 --providers=21 --customers=40 --orders=25 --reviews=15 --fresh-demo
```

> ⚠️ **JANGAN jalankan di production.** Perintah ini **menolak berjalan** ketika `APP_ENV=production` kecuali diberi `--force` **dan** konfirmasi interaktif. Dataset ditandai `is_demo=1` di seluruh tabel utama — `--fresh-demo` hanya menghapus baris demo, data produksi/customer asli tidak pernah disentuh. Menjalankan perintah kedua kali tanpa `--fresh-demo` akan **ditolak** (idempotent guard).

**Kondisi yang dipenuhi dataset demo:**

- Tepat **10.000 service listing aktif** terdistribusi ke **21 kategori blueprint** (normalisasi largest-remainder, di-assert seeder)
- Judul/deskripsi/harga Bahasa Indonesia **spesifik per kategori** dari dictionary (bukan lorem ipsum), slug unik
- Harga IDR integer dalam range realistis per kategori (mis. Cleaning Rp50rb–2,5jt; Renovation Rp500rb–250jt; Construction s.d. Rp1M)
- Provider: 60% individual/freelancer, 30% vendor, 10% company (partner_organizations + members), verifikasi 75% verified; **level badge dihitung dari data** (completed_jobs + rating) mengikuti logika existing — tidak ada badge palsu
- Lokasi realistis ter-konsentrasi ke Jabodetabek, Bandung, Surabaya, dll. (koordinat = jitter pusat kota valid dari LocationSeeder)
- Order: subset finance-complete dijalankan **lewat domain services asli** (`OrderService` → `PaymentService` sandbox webhook → `OrderStateMachine` → `SettlementService` → `RefundService`/`WithdrawalService`) — **ledger double-entry tetap balanced**, Σ debit = Σ credit
- Review: hanya untuk order `completed|settled|closed` (1 review/order), rating 5★ ~70% / 4★ ~21% / 3★ ~6%, dimension ratings mengikuti `Category.config.review_dimensions`, rating partner dihitung ulang dari agregat (bukan ditulis manual)
- Project marketplace + proposal + kontrak + milestone; RFQ + quotation (open/closed/awarded); corporate (branches, departments, cost centers, approval policies, CSR + PO)
- Media: pool gambar lokal deterministik per kategori (360 file: cover WebP 1200×800, avatar SVG provider, banner kategori — tanpa download jaringan, tanpa hotlink eksternal); 100% service punya cover, 70% punya 2+ galeri, avatar semua provider terisi
- Homepage/explore/admin langsung penuh: kategori, jasa populer, provider terverifikasi, review, blog, SEO metadata

**Akun demo (hanya lokal/demo):**

| Role | Email | Password |
|---|---|---|
| Customer | `customer@jasapedia.test` | `password` |
| Provider | `provider@jasapedia.test` | `password` |
| Company (vendor) | `company@jasapedia.test` | `password` |
| Corporate | `corporate@jasapedia.test` | `password` |
| Admin | `admin@jasapedia.test` | `password` (InitialAdminSeeder) |

Kredensial demo **tidak pernah ditampilkan** di output command ketika `APP_ENV=production`.

Env terkait (`config/demo.php`): `DEMO_DATA_ENABLED=false` (master switch untuk auto-seed via `db:seed`), `DEMO_SEED=20260901`, `DEMO_SERVICES=10000`, `DEMO_EMAIL_DOMAIN=example.test` (semua email demo memakai domain non-routable).

## Testing

```bash
php artisan test
```

157 test / 3.600+ assertion meliputi: unit (Money/TOTP/pricing/availability/finance-invariant), feature (auth/RBAC/partner/catalog/order/payment/chat/notif/demo-dataset), **E2E kritis**:

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
