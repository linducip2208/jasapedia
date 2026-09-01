# 06 — Domain Model (aggregate map + key entities)

## Aggregates & ownership

**Identity**: `User`(status: ACTIVE/SUSPENDED/LOCKED, phone, email) → UserProfile, CustomerProfile, UserDevice, TwoFactorSecret. `Role/Permission` (Authorization module).

**Customer**: `CustomerAddress`(subdistrict, lat/lng, is_default, notes) — full address hidden until operationally required (§19).

**Partner**: `Partner`(type: FREELANCER/INDIVIDUAL/VENDOR_COMPANY, verification_state, online_status) → PartnerProfile, PartnerDocument, PartnerSkill, PartnerServiceArea. `PartnerOrganization`(vendor) → PartnerMember(role: OWNER/MANAGER/DISPATCHER/FINANCE/PM/WORKER, permissions scope). Bank destinations: `PayoutDestination`.

**Catalog**: `Category`(tree, slug, config JSON: review_dimensions, cancellation_policy_id, warranty_policy_id, sla_defaults) → `ServiceTemplate` → `Service`(owner=Partner|Organization, fulfillment_type, delivery_mode, status, emergency_capable) → `ServicePackage`, `ServiceAddon`, `ServicePrice`(model: FIXED/PER_UNIT/HOURLY/DAILY/STARTING_FROM/PACKAGE/QUOTATION/MILESTONE + base + unit + min). `CategoryAttribute` (definitions, per category, typed: text/number/select/multiselect/file/boolean).

**Availability**: `PartnerSchedule`(weekly hours) + `PartnerBlock`(leave/holiday/blocked) + capacity + travel buffer → `AvailabilityService::slots()`; booking holds slots with row-level locks + unique covering index.

**Order**: `Booking`(snapshot JSON) → `Order`(code, type: SERVICE/PROJECT/MILESTONE_FUNDING/ADDITIONAL…) → `OrderItem`(name, qty, unit_price, amount — all frozen) → `OrderStatusHistory`(immutable). `Assignment`(order → partner/member), `WorkLog`, `CheckIn`(gps/otp), `ServiceEvidence`(before/after), `Material`, `AdditionalChargeRequest`(PENDING→APPROVED→billed).

**Deal-making**: `Project`(budget min/max, type FIXED/HOURLY/BUDGET_RANGE) → `Proposal` → (award) → `Contract`(versions + amendments) → `Milestone`(states §38) → `MilestoneDeliverable`, `MilestoneSubmission`. `Rfq` → `Quotation`(versioned, line_items JSON) → approval.

**Chat**: `Conversation`(type, context_type+context_id) ↔ `ConversationParticipant` → `Message`(client_message_id unique, type, body, structured JSON) → MessageAttachment/MessageRead/MessageReport; `ConversationEvent`.

**Money**: `PaymentTransaction`(gateway, intent, states §49) → WebhookEvent (idempotent by provider_event_id). `LedgerAccount`(type: ASSET/LIABILITY/REVENUE/EXPENSE/EQUITY, owner polymorphic) → `LedgerTransaction`(immutable, entries must balance) → `LedgerEntry`(immutable, debit/credit). Wallet = projection. `CommissionRule`(scopes) → `Commission` (snapshot). `Settlement`, `Withdrawal`, `Refund`, `ReconciliationDiff`.

**Trust**: `KycSubmission`/`KybSubmission`(states §63), `RiskFlag`, `Report`, `Block`, `Dispute`(states §66) → DisputeEvidence/Decision, `WarrantyPolicy`→`WarrantyClaim`, `Review`(dimension ratings, provider response, moderation).

**Growth/Ops**: Promotion/Voucher/VoucherRedemption, Referral, Membership; RecurringSchedule→occurrences; SlaPolicy→SlaTimer; SupportTicket(+messages); CmsPage/Block/Banner, BlogPost, SeoMeta; AuditLog, SystemSetting, Notification(+preferences).
