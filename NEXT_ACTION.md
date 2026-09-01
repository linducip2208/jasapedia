# NEXT_ACTION.md

- Current phase: **Backend core COMPLETE (Phases 0–50) — 101 tests / 485 assertions green**
- Open work (priority order):
  1. Web UI layer (customer PWA, partner, admin) — Phase 41–43 dashboards + 48 polish ride on this
  2. Flutter clients (customer + partner) — API stable
  3. Membership billing cycle
  4. Quotation→order conversion wiring (survey→quotation→order path)
  5. Upload hardening pass 2 (mime/size validation at disk layer)
  6. Load testing + browser E2E
- Constraints locked: money=int IDR, ledger immutable, RBAC server-side, fulfillment config-driven, AI advisory-only
