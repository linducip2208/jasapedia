# 00 — MASTER PRD (Locked v1.0)

Jasapedia — "Semua Jasa, Satu Platform." A full **service commerce platform**: marketplace for home services, professional services, digital services, freelancers, field technicians, vendor companies, projects, RFQ, contracts, and corporate procurement.

## 1. Product Pillars
1. **Transactional marketplace** (book & pay: Rp50.000 → Rp100.000.000+)
2. **Fulfillment engine** (config-driven: instant, appointment, hourly, per-unit, survey, quotation, RFQ, project/milestone)
3. **Money integrity** (ledger, commission, settlement, withdrawal, refund — invariant-tested)
4. **Trust & safety** (KYC/KYB, disputes, warranty, risk, reviews)
5. **B2B procurement** (corporate orgs, approval matrix, consolidated billing)
6. **Platform services** (chat, notifications, CMS, SEO, search, promo, analytics, AI-assist)

## 2. Personas
Guest · Individual Customer · Corporate Customer(+managers) · Freelancer/Individual Partner · Field Technician · Vendor Company(+Owner/Manager/Dispatcher/Finance/PM/Worker) · Internal staff (Admin, Ops, Dispatcher, Finance, CS, KYC, T&S, Marketing, Content, Auditor, Viewer).

## 3. Locked MVP Categories (§134)
Cleaning (HOURLY+ONSITE) · AC Service (PER_UNIT+APPOINTMENT+ONSITE) · Handyman (SURVEY→QUOTATION) · Programming (PROJECT+MILESTONE+REMOTE). Architecture supports all 21 blueprint categories.

## 4. Core Flows (must E2E)
- **Home service**: search → book → pay → dispatch → field work → additional charge → complete → settle → review
- **Project**: post → proposals → shortlist → award → contract → milestones → funding → work → approval → release → review
- **Corporate**: request → approval matrix → order/RFQ → service → consolidated billing
- **Dispute / Withdrawal / Refund** flows as per modules 64/58/59.

## 5. Non-Negotiable Rules
- Backend recalculates all prices; snapshots frozen per transaction.
- All state transitions explicit; full immutable history.
- Money = integers. Ledger double-entry immutable; corrections via reversing entries.
- Authorization server-side everywhere (RBAC matrix doc 09).
- Chat commercial actions are structured records, never free text.
- AI never autonomously touches money/KYC/disputes/accounts.

Full detail: docs 01–08, 10–20.
