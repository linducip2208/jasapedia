# 10 — ORDER STATE MACHINE (locked)

```
DRAFT → PENDING_PAYMENT → PAID → SEARCHING_PROVIDER → OFFERED → ACCEPTED
      → ASSIGNED → ON_THE_WAY → ARRIVED → CHECKED_IN → WORKING
      → AWAITING_CUSTOMER_CONFIRMATION → COMPLETED → SETTLEMENT_PENDING → SETTLED → CLOSED
```
Exceptions (reachable per rules below): CANCELLED, EXPIRED, FAILED, DISPUTED, REWORK_REQUIRED, REFUND_PENDING, PARTIALLY_REFUNDED, REFUNDED.

## Transition table (legal edges)
| From | Allowed → | Actor/Rule |
|---|---|---|
| DRAFT | PENDING_PAYMENT, CANCELLED | checkout |
| PENDING_PAYMENT | PAID, FAILED, EXPIRED, CANCELLED | gateway callback / TTL expiry (scheduler) |
| PAID | SEARCHING_PROVIDER, REFUND_PENDING | auto-dispatch starts |
| SEARCHING_PROVIDER | OFFERED, CANCELLED, REFUND_PENDING | dispatch engine/manual |
| OFFERED | ACCEPTED, SEARCHING_PROVIDER, EXPIRED | partner accept / offer TTL |
| ACCEPTED | ASSIGNED, ON_THE_WAY, CANCELLED, DISPUTED | vendor internal assignment or self |
| ASSIGNED | ON_THE_WAY, CANCELLED, DISPUTED | technician |
| ON_THE_WAY | ARRIVED, CANCELLED, DISPUTED | GPS/OTA |
| ARRIVED | CHECKED_IN | OTP verify |
| CHECKED_IN | WORKING | after before-evidence |
| WORKING | AWAITING_CUSTOMER_CONFIRMATION, REWORK_REQUIRED | after after-evidence (+additional charges settled) |
| AWAITING_CUSTOMER_CONFIRMATION | COMPLETED, DISPUTED, REWORK_REQUIRED | customer confirm / timeout auto-complete (SLA policy) |
| COMPLETED | SETTLEMENT_PENDING | commission computed, hold clock |
| SETTLEMENT_PENDING | SETTLED, DISPUTED, REFUND_PENDING | settlement engine |
| SETTLED | CLOSED, DISPUTED | no open disputes + review window ok |
| REWORK_REQUIRED | WORKING, DISPUTED, CANCELLED | rework attempt |
| any-active | DISPUTED | dispute opened (snapshot active_status) |
| DISPUTED | (restore from snapshot), REFUND_PENDING, SETTLEMENT_PENDING | dispute resolution |
| SETTLEMENT_PENDING/SETTLED | REFUND_PENDING | approved refund |
| REFUND_PENDING | PARTIALLY_REFUNDED, REFUNDED | refund executed (ledger reversal) |
| PARTIALLY_REFUNDED | REFUNDED | remainder refund |

Rules: unknown edge = DomainException; every transition writes `order_status_history` (from,to,actor,reason,metadata); DISPUTED stores `active_status_snapshot`.
