# 12 — CHAT SPEC (summary)

Conversations: DIRECT, ORDER, PROJECT, RFQ, SUPPORT, DISPUTE, GROUP. Context links: SERVICE/ORDER/PROJECT/RFQ/PROPOSAL/QUOTATION/CONTRACT/MILESTONE/SUPPORT_TICKET/DISPUTE.

Message types: TEXT, IMAGE, VIDEO, AUDIO, FILE, LOCATION, SYSTEM_EVENT, SERVICE_CARD, ORDER_CARD, QUOTATION_CARD, PAYMENT_REQUEST, MILESTONE_CARD, RESCHEDULE_REQUEST, ADDITIONAL_CHARGE_REQUEST, DISPUTE_EVENT, WARRANTY_EVENT.

Features: reply_to, delivered/read receipts, typing (ephemeral broadcast), attachments (private disk, signed URLs), search, mute, report, participants w/ roles, system events, offline retry via client_message_id unique, idempotent sends.

Commercial safety: money-affecting actions (additional charge approve, milestone approve, quotation approve, payment) happen ONLY via structured endpoints producing structured records + domain events. Chat cards deep-link to those endpoints. Structured messages carry `action_state` (PENDING/APPROVED/REJECTED/EXPIRED) mirrored from source entity.

Masking: phone/email patterns detected → warning banner + optional auto-mask per policy; flagged events to Risk. No content surveillance beyond policy detection.
