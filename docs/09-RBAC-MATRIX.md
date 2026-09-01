# 09 — RBAC MATRIX (summary; enforced server-side)

## Roles
Customer, Partner, VendorOwner, VendorManager, VendorDispatcher, VendorFinance, VendorPM, VendorWorker, SuperAdmin, PlatformAdmin, OpsManager, Dispatcher, FinanceManager, FinanceStaff, Support, KycOfficer, TrustSafetyOfficer, DisputeOfficer, MarketingManager, ContentManager, Auditor, ManagementViewer.

## Permission namespaces & highlights
`customer.order.*` (create/cancel/confirm/review/dispute) · `customer.address.manage` · `partner.profile.manage` · `partner.order.accept|reject|on_the_way|checkin|complete` · `partner.withdrawal.request` · `vendor.member.manage` · `vendor.assignment.manage` · `vendor.finance.view` · `admin.category.manage` · `admin.service.review` · `ops.order.assign|reassign|incident` · `finance.refund.approve|execute` · `finance.settlement.execute` · `finance.withdrawal.approve|process` · `finance.reconciliation.manage` · `kyc.review|approve|reject` · `dispute.manage|resolve` · `ts.report.handle` · `marketing.voucher.manage` · `content.cms.manage` · `corporate.request.approve` · `support.ticket.*` · `audit.view` · `settings.manage`.

## Enforcement
- `role_has_permissions` via middleware `permission:finance.refund.approve`
- Policies per model (OrderPolicy, RefundPolicy, WithdrawalPolicy, etc.)
- Scoped: vendor roles only see own org (global scope via membership context), corporate managers only own org branches
- Admin 2FA-ready flag on role; privileged = SuperAdmin/Finance*/Dispute*
- API tokens: Sanctum ability strings = permission list

Seed data locks: SuperAdmin all; roles composed in `RolesAndPermissionsSeeder` (single source).
