# 16 — QA TEST PLAN (summary)

Layers: Unit (Money, state machines, pricing calc) · Domain (actions/services) · Feature (HTTP per app) · API (v1 contract) · RBAC (every permission pair) · Integration (payment sandbox, ledger) · Concurrency (booking slot, webhook dedupe, settlement, withdrawal, refund race, wallet spend) · E2E critical (home service §126, project §127, corporate, dispute, withdrawal) · Security (authz matrix, upload, webhook signature, rate limit).

Conventions: RefreshDatabase (MySQL), `Money::` integer assertions, seeded RBAC in tests via `RolesAndPermissionsSeeder`, sandbox gateway only. CI gate: full suite green + ledger invariants + critical E2E.

Tooling: PHPUnit (bundled). Browser E2E deferred until UI stabilizes (documented in FINAL_AUDIT).
