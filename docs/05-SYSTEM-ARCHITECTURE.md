# 05 — System Architecture

## Topology
**Modular monolith (Laravel 13 / PHP 8.3 / MySQL 8 / Redis)** + API-first design. Flutter clients later consume `/api/v1`.

```
apps (logical, inside monolith):
  customer-web (Blade PWA, routes/customer.php)
  partner-web  (Blade, routes/partner.php)
  admin        (Blade, routes/admin.php)
  api/v1       (REST, routes/api.php → Sanctum)
packages (internal):
  app/Support       — shared kernel: Money, ApiResponse, states, base classes
  app/Domain/<M>    — 40+ modules, each: Models, Actions, Services, Events, Data, Policies, Enums
```

## Module Map (blueprint §8 grouping)
| Group | Modules (Domain namespaces) |
|---|---|
| Identity & Access | Identity (users, auth, 2FA), Authorization (RBAC), Audit |
| People | Customer, Partner, PartnerOrganization, Corporate |
| Catalog | Catalog (categories/attributes/templates/services/packages), Pricing |
| Places & Time | Location, ServiceArea, Availability |
| Commerce | Booking, Order, Dispatch, FieldService, Material |
| Deal-Making | Project, Rfq, Proposal, Quotation, Contract, Milestone, Worklog |
| Communication | Chat, Notification |
| Money | Payment, Wallet, Ledger, Commission, Settlement, Withdrawal, Refund, Reconciliation |
| Growth | Promotion, Voucher, Referral, Membership |
| Trust | KycKyB, TrustSafety, Risk, Dispute, Warranty, Review |
| Ops | Support, Recurring, Sla, Search, Recommendation, Cms, Seo, Analytics |
| Platform | Configuration, MediaStorage, AiIntegration |

## Cross-Cutting
- **Money**: `App\Support\Money\Money` (int minor units); all DB money columns `BIGINT UNSIGNED`.
- **State machines**: enum-based `Transition` maps in each domain (`States.php`), enforced in services; every change logged to `<entity>_status_history` or `audit_logs`.
- **Authorization**: granular permissions (dot-namespaced), role assignment, policies + middleware, scoped to org where relevant.
- **Events/Queues**: domain events → queued jobs (email, push adapters, settlement checks, SLA timers via scheduler).
- **Realtime**: broadcast-ready (`BROADCAST_CONNECTION`), DB is source of truth; chat falls back to polling.
- **Storage**: `files` disk local, S3-compatible in prod; uploads validated (mime/size/extension), private disk + signed URLs.
- **API**: `/api/v1`, Sanctum tokens, consistent envelope `{data|message|meta}`, cursor/offset pagination, idempotency keys on money mutations, rate limiting.
- **Errors**: `code/message/details/reference_id`; no internals leaked.

## Degradation (§122)
AI optional · realtime optional (poll) · notification adapter failure never breaks transactions · webhook delay recovered by reconciliation.
