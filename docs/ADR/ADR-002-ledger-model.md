# ADR-002 Ledger Model

**Status:** Accepted

Double-entry, immutable, integer IDR. Accounts chart in doc 13. Corrections only via reversing entries. Wallet balances are projections cached in Redis/DB, never source of truth. Commission stored as snapshot rows (never recalculated retroactively). Uniqueness: one settlement per order (unique index), refund validation reads paid−refunded from ledger, withdrawal locks account rows in transaction.
