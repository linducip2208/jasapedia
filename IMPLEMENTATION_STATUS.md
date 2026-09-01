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
| 16–21 Project/RFQ/Proposal/Quotation/Contract/Milestone | ⬜ | |
| 22–27 Ledger/Commission/Settlement/Withdrawal/Refund/Reconciliation | 🔶 | Ledger (balanced, reversal-only), Commission snapshot (immutable), Settlement (double-guard), Withdrawal (reservation+race-safe), Refund (eligibility+lock) — all invariant-tested (§54/§128) green. Reconciliation pending |
| 28–32 KYC/T&S/Dispute/Warranty/Reviews | ⬜ | |
| 33–37 Corporate/Recurring/Promo/Referral/Membership | ⬜ | |
| 38–40 Support/CMS/SEO | ⬜ | |
| 41–43 Command Centers/Analytics | ⬜ | |
| 44–45 AI | ⬜ | |
| 46 Hardening | ⬜ | |
| 47 Full E2E | ⬜ | |
| 48 UI Polish | ⬜ | |
| 49 Prod Readiness | ⬜ | |
| 50 Final Audit | ⬜ | |
