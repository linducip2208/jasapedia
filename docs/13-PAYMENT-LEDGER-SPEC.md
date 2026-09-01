# 13 — PAYMENT & LEDGER SPEC (locked)

## Money
`Money` = int (IDR rupiah). DB `BIGINT UNSIGNED`. Never float. Format via `id_ID` only at display layer.

## Payment abstraction
`PaymentGatewayInterface`: `createIntent(PaymentRequest): PaymentIntent`, `verifyWebhook(Request): GatewayEvent`, `refund(ref, amount, reason): RefundResult`, `status(ref): PaymentStatus`. Adapters: `SandboxGateway` (default, deterministic), `ManualTransferGateway`; Midtrans/Xendit are future drop-ins (STOP §1.3: no merchant credentials).

## Payment states (§49)
CREATED→PENDING→PAID | FAILED | EXPIRED | CANCELLED; PAID→REFUND_PENDING→PARTIALLY_REFUNDED→REFUNDED; (AUTHORIZED reserved for future CC).
Webhooks: `payment_webhook_events` (provider, event_id unique → idempotency), signature verify, store-then-process in transaction; never trust frontend status.

## Ledger (double-entry, immutable)
- `ledger_accounts`: code, type ASSET/LIABILility/REVENUE/EXPENSE/EQUITY, owner (user/partner/platform/system), currency.
- `ledger_transactions`: group, reference (order/refund/withdrawal/settlement…), immutable.
- `ledger_entries`: tx, account, debit/credit, amount_bigint, running note. **Constraint: Σdebit=Σcredit per tx (service-enforced + invariant test).**
- Corrections = reversing entries only. `wallet.balance` = projection (cache), truth = SUM(entries).

## Canonical chart of accounts (platform scope)
| Code | Account | Type |
|---|---|---|
| 1001 | Cash – Gateway Clearing | ASSET |
| 1002 | Bank Operating | ASSET |
| 1101 | Customer Wallets (float) | ASSET |
| 2101 | Vendor Payable | LIABILITY |
| 2102 | Vendor Withdrawal Clearing | LIABILITY |
| 2103 | Refunds Payable | LIABILITY |
| 2201 | Vendor Wallets (float) | LIABILITY |
| 4101 | Platform Service Fee Revenue | REVENUE |
| 4201 | Commission Revenue | REVENUE |
| 4301 | Membership Revenue | REVENUE |
| 4901 | Promotion Expense (platform-funded) | EXPENSE |
| 4902 | Payment Gateway Fee Expense | EXPENSE |
| 4903 | Withdrawal Fee Revenue | REVENUE |

## Flows (entry patterns)
1. **Customer pays order**: DR 1001 / CR 2201 VendorPayableHold(net after fee), CR 4101+4201 (platform) — commission snapshot at payment time.
2. **Settlement complete**: DR 2201 / CR 2101→2102.
3. **Withdrawal executed**: DR 2102 / CR 1002 (+DR fee→4903 CR 4103? keep simple: DR 2102 / CR 1002, fee DR 4902 or CR 4903).
4. **Refund**: reverse proportional entries DR 4101/4201(−) & CR 1001; vendor portion DR 2201.
5. **Promotion platform-funded**: DR 4901 / CR 2101 (reduces vendor payable) or CR 2201.
6. **Wallet top-up/use**: DR 1101 adjustments with customer wallet projection.

## Invariant tests (must pass, Phase 22+)
Σdebit=Σcredit per tx · no double settle (unique index) · refund ≤ eligible paid − refunded · withdrawal ≤ available (LOCK) · duplicate webhook = single movement · commission immutable (no UPDATE, only reversal) · wallet projection == ledger sum.
