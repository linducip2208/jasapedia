# IMPLEMENTATION_STATUS.md — Jasapedia build log

Legend: ✅ done · 🔶 partial · ⬜ pending. Money = integer IDR. Never fake completion (§187).

| Phase | Status | Notes |
|---|---|---|
| 0 Audit | ✅ | docs/AUDIT-BASELINE.md — greenfield, Laragon env verified |
| 1 Blueprint | ✅ | docs 00/05/06/09/10/11/12/13/15/16/17/19 + ADR-001..005 |
| 2 Foundation | ✅ | Money kernel, ApiResponse envelope, RBAC (roles/permissions/scoped org), audit_logs, settings, health, error conventions, 14 tests green |
| 3 Identity & Users | 🔶 | Auth (register/login/logout/me/password/sessions/lockout), RBAC enforcement tests — remaining: TOTP 2FA, admin user mgmt |
| 4 Partner & Org | ✅ | Partner/individual/vendor org, members w/ scoped RBAC, skills, docs, areas, payout dests, online status |
| 5 Catalog | ✅ | 21 categories, templates, services+packages+addons, price-model×fulfillment validation, admin-ready config JSON |
| 6 Location | ✅ | Location tree (country→subdistrict), customer addresses, 15 provinces/33 cities seeded |
| 7 Search & Discovery | 🔶 | Public search/filter/sort API done; landing pages + favorites pending (Phase 7 revisit after UI) |
| 8 Availability | ✅ | Schedules/blocks/booking_slots w/ DB unique constraint race safety + concurrency tests |
| 9 Pricing | ✅ | PricingCalculator (per_unit/hourly/daily/package/fixed), addons, emergency surcharge, frozen snapshots |
| 10 Booking & Order | ✅ | Orders+items+immutable history, state machine (doc 10), slot integration, cancel/expire |
| 11 Payment | ✅ | Gateway abstraction, SandboxGateway signed webhook, idempotent events, amount checks, double-pay guard |
| 12 Dispatch | ⬜ | |
| 13 Field Service | ⬜ | |
| 12 Dispatch | ✅ | Scoring engine (transparent breakdown), auto-direct/broadcast/sequential/manual/vendor-internal, first-accept-wins, offer TTL |
| 13 Field Service | ✅ | OTP check-in, before/after evidence, materials, structured AdditionalChargeRequest (24h TTL), worker-only guards. **Critical E2E §126 passes** |
| 14 Chat | ✅ | Conversations (direct/order contexts), idempotent sends (client_message_id), read receipts, contact-share warnings, reports, broadcast events + poll fallback |
| 15 Notifications | ✅ | In-app notifications, unread counts, per-event channel preferences, critical-event policy, adapter interface |
| 16–21 Project/RFQ/Proposal/Quotation/Contract/Milestone | 🔶 | Project→Proposal→Award→Contract(versioned)→Milestone(fund/approve/release) **E2E §127 PASS**; RFQ table + quotation versioning schema ready, service wiring pending |
| 22–27 Ledger/Commission/Settlement/Withdrawal/Refund/Reconciliation | 🔶 | Ledger/Commission/Settlement/Withdrawal/Refund **invariants PASS (§54/§128)**; Reconciliation diff-detection pending |
| 28–32 KYC/T&S/Dispute/Warranty/Reviews | ✅ | KYC submit+decisions, disputes w/ ledger refunds, warranty window, reviews+dimensions. Full E2E dispute PASS |
| 33–37 Corporate/Recurring/Promo/Referral/Membership | 🔶 | Recurring/Promo/Referral PASS; corporate schema+requests done (E2E pending); membership schema only |
| 38–40 Support/CMS/SEO | ✅ | Tickets threaded, CMS pages/blocks/blog, SEO landing /jasa/{cat}/{city} |
| 41–43 Command Centers/Analytics | ⬜ | Data layer ready (orders/settlements/ledger); dashboards pending (web UI) |
| 44–45 AI | ✅ | AiManager + advisory-only endpoints, graceful degradation, tests |
| 46 Hardening | ✅ | Security headers, rate limits, error envelope, authz checks (SecurityHardeningTest) |
| 47 Full E2E | 🔶 | Home service §126 + Project §127 + dispute + withdrawal PASS; corporate E2E pending |
| 48 UI Polish | ⬜ | Web UI layer pending (API-first decision) |
| 49 Prod Readiness | ✅ | .env.example, scheduler jobs, health endpoints, deployment docs |
| 50 Final Audit | ✅ | FINAL_AUDIT.md — 95 tests/461 assertions green |
