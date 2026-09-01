# NEXT_ACTION.md

- Current phase: **2 — Foundation**
- Completed: Phase 0 (audit), Phase 1 (blueprint docs + ADRs)
- Constraints: modular monolith `app/Domain/*`; money=int IDR; RBAC seeded single-source; API /api/v1 + Sanctum; MySQL 8 + Redis (predis); Blade+Tailwind+Alpine UI
- Next exact action: scaffold foundation — support kernel (Money, ApiResponse, BaseEnum), RBAC migrations+seeder+middleware, audit_logs, system_settings, health endpoints, then run tests → Phase 3 Identity.
