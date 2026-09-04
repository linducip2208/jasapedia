# Jasapedia

**"Semua Jasa, Satu Platform."**

Platform *service commerce* lengkap: marketplace jasa rumah tangga, jasa profesional, jasa digital, freelancer, teknisi lapangan, perusahaan vendor, project-based work, RFQ, kontrak, dan pengadaan korporat — dalam satu ekosistem.

## Stack

| Layer | Teknologi |
|---|---|
| Backend | Laravel 13 / PHP 8.3 / MySQL 8 / Redis |
| Auth | Sanctum (API tokens) + TOTP 2FA untuk akun privileged |
| Frontend | API-first (`/api/v1`, 223 routes) + Web UI Blade/Tailwind — siap untuk Flutter (customer & partner app) |
| Realtime | Broadcast events (Reverb-compatible), DB sebagai source of truth, fallback polling |
| Money | Integer IDR, double-entry immutable ledger, snapshot harga per transaksi |
| Media | `MediaService` disk-agnostic (`MEDIA_DISK`: public/s3/r2), validasi MIME asli (finfo) + magic bytes + size cap |

---

## Fitur

### 1. Customer Storefront (Web + API)

- **Home super-app** — hero, 21 kategori, 6 entry-point (Cari Jasa / Booking Teknisi / Posting Kebutuhan / Cari Freelancer / Buat Proyek / Business), Jasa Populer, how-it-works, trust section, CTA provider & business
- **Explore & pencarian** — `SearchProviderInterface` (SQL aktif, Meilisearch-ready via `SEARCH_DRIVER`), filter kategori/kota/harga/rating, filter chips + drawer mobile, sorting, suggest endpoint
- **Detail jasa 2.0** — galeri media, sticky purchase panel, jadwal, emergency surcharge, garansi, ulasan terverifikasi, paket & add-on
- **Checkout & order** — pricing quote backend-authoritative (harga dibekukan dalam snapshot), pemilihan slot, alamat snapshot terkunci, emergency capable
- **Pembayaran** — sandbox gateway di web, webhook signed + idempotent, amount-mismatch guard, double-pay guard; adapter produksi **Xendit** & **Midtrans** via `PAYMENT_DRIVER`
- **Pelacakan order** — timeline status real, konfirmasi penyelesaian, OTP check-in (customer memverifikasi teknisi), cancel dengan aturan state machine
- **Ulasan** — 1 review/order (hanya order selesai), rating berdimensi mengikuti config kategori, rating partner dihitung ulang dari agregat
- **Favorit** — toggle DB-unique untuk jasa & penyedia, halaman favorit
- **Posting Kebutuhan (RFQ)** — wizard publikasi + attachment foto, deadline, visibility (public/invited), bandingkan & terima penawaran (quotation versioned, immutable approval)
- **Project Marketplace** — publikasi proyek, terima proposal, shortlist/award, kontrak dua pihak, milestone: fund → kerjakan → revisi → approve → **release + ledger otomatis**
- **Chat** — percakapan per-order & direct, idempotent (`client_message_id`), read receipts, lampiran, polling fallback (broadcast-ready)
- **Notifikasi** — notification center, unread count, preferensi per event×channel, kebijakan event kritis
- **Account center** — dashboard, orders per status, profil, alamat multi (lat/lng), notifikasi
- **Blog & CMS** — blog posts, halaman statis (`/halaman/{slug}`), CMS blocks homepage

### 2. Partner / Provider Center (Web + API)

- **Onboarding & KYC** — registrasi partner (freelancer/individual/vendor company), submit KYC/KYB, dokumen, keputusan verifikasi oleh admin + audit log
- **Organisasi vendor** — partner_organizations + members dengan scoped RBAC (owner/manager/dispatcher/finance/PM/worker), skills, service area (city/radius), payout destinations
- **Manajemen jasa** — CRUD service + upload galeri (MIME-hardened), paket, add-on, harga per model (per_unit/hourly/daily/package/fixed), pause/activate
- **Ketersediaan** — jadwal mingguan, blocks/leave, slot engine **race-safe** (unique constraint + concurrency test)
- **Order inbox** — terima/mulai/selesaikan pesanan, status transitions via state machine
- **Dispatch** — penawaran tugas dengan scoring transparan (rating, jarak, acceptance rate), mode auto-direct/broadcast/sequential/manual/vendor-internal, first-accept-wins, offer TTL
- **Operasi lapangan (API `/field/*`)** — on-the-way → arrived → OTP check-in → start work → evidence before/after → materials → **AdditionalChargeRequest terstruktur** (24h TTL; chat text tidak pernah mengubah nominal) → submit completion
- **RFQ & quotation** — lihat kebutuhan terbuka, kirim/revise penawaran (versioned)
- **Proyek & kontrak** — submit/withdraw proposal, milestone start/submit, work logs
- **Keuangan** — saldo, riwayat settlement, withdrawal (reservasi race-safe, min amount, double-completion blocked), tambah rekening payout
- **Ulasan** — baca + tanggapi ulasan

### 3. Jasapedia Business (Korporat)

- **Organisasi korporat** — branches, departments, cost centers, employees dengan role & spend limit
- **Approval matrix** — threshold manager + finance, require_category_approval, allowed categories
- **Budget** — alokasi per cost center per periode dengan pemakaian terpakai
- **Service request** — employee → request → approval dua level → konversi ke order dengan **PO reference tersimpan**
- **Dashboard korporat** — ringkasan approval, request, pengeluaran

### 4. Admin Command Center (`/admin`)

- **Shell AdminLTE 4.9.1** (Bootstrap 5, dark mode `data-bs-theme`), diisolasi sebagai Vite entry terpisah (`resources/css/admin.css` + `resources/js/admin.js`) — Bootstrap tidak pernah termuat di storefront Tailwind

- **Dashboard metrik real** — GMV, order volume, cancel/dispute rate, komisi ledger, operasi field, **ledger balance check**
- **Pesanan** — monitoring seluruh order + status history immutable
- **Verifikasi penyedia** — KYC/KYB lifecycle (approve/reject + catatan + audit)
- **Keuangan** — withdrawal lifecycle (review/process/complete/fail), balance check
- **Sengketa** — resolve dispute dengan opsi full/partial refund → eksekusi lewat ledger
- **Pengguna** — manajemen user + audit log aksi sensitif
- Akses dikontrol permission granular `admin.*` / `audit.view` / `reports.view`

### 5. Keuangan & Ledger (domain core)

- **Ledger double-entry** — Σdebit = Σcredit diuji di setiap posting + global invariant; koreksi hanya via *reversing entries* (append-only, tidak pernah delete baris uang)
- **Commission** — snapshot immutable (unique per order), rate dari settings
- **Settlement** — gross − additional − commission = vendor_net; double-settle guard
- **Withdrawal** — serialisasi per partner + reservation accounting, race-safe
- **Refund** — eligibility (≤ paid − refunded) + lock konkuren; refund dispute dieksekusi ke ledger
- **Reconciliation** — deteksi diff payment/payout/ledger
- Semua nilai **integer IDR** — tidak ada float money

### 6. Trust & Safety

- **KYC/KYB** — submission, officer decision, audit trail
- **Dispute** — alur opened → evidence → mediation → decision, refund berbasis ledger
- **Warranty claims** — window validasi dari config kategori
- **Review moderation** — laporan ulasan/pesan, status published/hidden
- **Chat safety** — contact-share warning, report pesan, block user

### 7. Growth

- **Layanan berulang (recurring)** — jadwal mingguan/bulanan, materialisasi occurrence idempotent
- **Promosi & voucher** — type percent/fixed, max discount, min spend, usage/per-user limit, first-order-only, stackable, vendor share
- **Referral** — kode deterministik, kualifikasi, reward
- **Membership** — plans (schema live; billing cycle menyusul)

### 8. AI (advisory-only)

- `AiManager` + provider abstraction, graceful degradation tanpa kredensial
- Endpoint: cari jasa, build brief dari kebutuhan, ringkasan percakapan — saran AI tidak pernah mengubah data transaksional

### 9. Platform & Infra

- **RBAC** — 24 role, 70+ granular permission, scoped org permissions, middleware `permission:`
- **Auth** — register (purpose-based), login, sessions (list/revoke), lockout, reset password, **TOTP 2FA RFC-6238** wajib untuk akun privileged
- **Audit log** — actor, before/after, IP, user-agent untuk aksi sensitif
- **Location** — tree Indonesia (country→subdistrict), 15 provinsi / 33 kota seeded, alamat customer dengan lat/lng
- **Search & Geo abstraction** — `SearchProviderInterface`, `GeoServiceInterface` (haversine + radius filter)
- **Media** — `MediaService` (MIME asli + magic bytes + size cap), `service-image` component dengan fallback ikon kategori
- **PWA** — manifest + service worker (static-only cache; API/admin network-only), installable
- **Health & observability** — `/api/v1/health` (DB+Redis) + `/up`, structured logs
- **Hardening** — security headers, rate limiters (api/auth/webhook), error envelope tanpa bocoran internal, CSRF, XSS escaping, ownership checks di semua route
- **Scheduler** — order expiry, recurring materialization, TTL cleanup (ACR, offers)

### 10. Web Surface (route map)

| Area | Prefix | Isi |
|---|---|---|
| Customer | `/`, `/explore`, `/jasa/*`, `/checkout`, `/orders`, `/favorit` | storefront, detail jasa, checkout, pelacakan, favorit |
| Akun | `/akun/*` | dashboard, profil, notifikasi, alamat |
| Kebutuhan (RFQ) | `/kebutuhan/*` | wizard, penawaran, terima quotation |
| Proyek | `/proyek/*` | publikasi, proposal, kontrak + milestone |
| Chat | `/chat/*` | daftar percakapan, room, lampiran |
| Penyedia | `/penyedia/{slug}` | profil publik + level transparan (New→Verified→Preferred→Top→Pro dari metrik nyata) |
| Partner Center | `/partner/*` | dashboard KPI, jasa, pesanan, kebutuhan, proyek, keuangan, ulasan, onboarding + KYC |
| Business | `/business/*` | landing + dashboard korporat |
| Admin | `/admin/*` | command center (AdminLTE 4, layout gelap terpisah) |
| Konten | `/blog`, `/halaman/*` | blog + CMS pages |

Error pages 403/404/500 berbahasa Indonesia; branding original `<x-brand.*>` (tanpa branding Laravel).

### 11. API Surface (`/api/v1` — 150 endpoint)

`auth` (register/login/2FA/sessions/password) · `catalog` (categories/services/locations, CRUD jasa partner) · `addresses` · `orders` (quote/store/cancel/confirm/checkin/ACR decide) · `field` (offers, accept/reject, on-the-way/arrived/checkin/start-work/evidence/materials/ACR/submit) · `chat` (direct, per-order, messages, read, report) · `notifications` (+preferences) · `projects` (proposal decide/contract/milestones fund-approve-revision-release) · `partner/deals` (projects, proposals, contracts, milestones, worklogs) · `rfqs` + `quotations` · `reviews` + `disputes` · `corporate` (orgs, employees, policy, requests, approve, convert) · `ai` · `cms`/`blog`/SEO landing · `support` tickets · `partner` (profil, verification, skills, documents, service areas, payout, members) · `payments` · `health`

Desain **API-first**: seluruh domain dapat diakses Flutter/mobile tanpa duplikasi logika.

---

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
- Seluruh insert dibatch **chunk 500** (tidak ada 10.000 record lewat HTTP), seeding deterministik (`mt_srand`), media diresolve lewat `MediaService::url()`

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

---

## Testing

```bash
php artisan test
npm run build   # frontend (Vite)
```

168 test / 5.900+ assertion meliputi: unit (Money/TOTP/pricing/availability/finance-invariant), feature (auth/RBAC/partner/catalog/order/payment/chat/notif), **demo dataset** (media integrity, distribusi service, invariant finansial), dan **E2E kritis**:

- **Home Service (§126)**: search → book → pay → dispatch → accept → OTP check-in → evidence → additional charge → complete ✓
- **Project (§127)**: post → proposal → shortlist → award → contract → milestone funding → revision → approval → **release + ledger** ✓
- **Corporate**: request → approval dua level → konversi order (PO ref tersimpan) ✓
- **Dispute**: settled order → dispute → evidence → officer → full refund → ledger balanced ✓
- **Invariants finansial (§54/§128)**: ledger balanced, double-settle/refund/withdrawal blocked, refund ≤ paid, dedup webhook ✓

---

## Setup (Laragon / lokal)

```bash
composer install
cp .env.example .env   # sesuaikan DB
php artisan key:generate
php artisan migrate --seed
npm install && npm run build
php artisan serve
```

Redis (opsional untuk dev): `D:\laragon\bin\redis\...\redis-server.exe --port 6379`

Seed admin: `admin@jasapedia.test / password` — **ganti di production**.

## Status & Known Gaps

Detail live: `IMPLEMENTATION_STATUS.md` + `FINAL_AUDIT.md`. Gap terbuka: membership billing cycle, custom offer di chat, Reverb realtime (fallback polling aktif), aktivasi Meilisearch, quotation → service order conversion, browser E2E (Playwright), programmatic SEO landing pages Blade.

## Dokumentasi

`docs/` — Master PRD, arsitektur, domain model, state machine (order/project), RBAC matrix, chat spec, payment & ledger spec, QA plan, ADR (001–005). Status implementasi live: `IMPLEMENTATION_STATUS.md`.

## Lisensi

Proprietary — © 2026 Jasapedia.
