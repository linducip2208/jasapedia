# NEXT_ACTION.md

- Current phase: **Super App web layer COMPLETE (customer/partner/admin/business) — 129 tests / 562 assertions green**
- Open work (priority order):
  1. Browser E2E (Playwright): scenarios A–E from the master brief
  2. Reverb realtime activation (chat currently poll-fallback, broadcast-ready)
  3. Custom Offer (chat → structured offer → order) + chat card renderers
  4. Quotation → service order conversion (survey→quotation→order path)
  5. Membership billing cycle (plans exist; billing/renewal/invoice pending)
  6. Programmatic SEO Blade landing pages (/jasa/{cat}/{city}/{district}) + sitemap.xml/robots.txt
  7. Meilisearch activation (index sync command + SEARCH_DRIVER=meilisearch) once infra exists
  8. Load testing + upload hardening pass 2 (image dimension checks)
- Constraints locked: money=int IDR, ledger immutable, RBAC server-side, fulfillment config-driven, AI advisory-only
- Before release: real Xendit/Midtrans credentials via env, APP_KEY rotation, npm run build, healthy-machine `vendor/bin/pint`
