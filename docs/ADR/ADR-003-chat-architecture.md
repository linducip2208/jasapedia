# ADR-003 Chat Architecture

DB is source of truth. `client_message_id` (uuid) UNIQUE per conversation for offline retry idempotency. Message types enum incl. structured cards (ORDER_CARD, QUOTATION_CARD, ADDITIONAL_CHARGE_REQUEST…) with `structured` JSON payload referencing domain records — free text never implies financial authorization. Reads table for receipts; typing indicator via broadcast presence (ephemeral, not persisted). Realtime via Reverb-compatible broadcast; client falls back to polling `/api/v1/conversations/{id}/messages?after=<id>`.
