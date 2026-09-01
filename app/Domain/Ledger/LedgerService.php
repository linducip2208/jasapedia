<?php

namespace App\Domain\Ledger;

use App\Domain\Auth\DomainException;
use App\Models\LedgerAccount;
use Illuminate\Support\Facades\DB;

/**
 * Double-entry immutable ledger (ADR-002, doc 13).
 * Money = integer IDR. Corrections only via reversing entries.
 */
class LedgerService
{
    /** Canonical platform accounts — auto-created. */
    public const CHART = [
        '1001' => ['Cash – Gateway Clearing', 'asset'],
        '1002' => ['Bank Operating', 'asset'],
        '1101' => ['Customer Wallets (float)', 'asset'],
        '2101' => ['Vendor Payable', 'liability'],
        '2102' => ['Vendor Withdrawal Clearing', 'liability'],
        '2103' => ['Refunds Payable', 'liability'],
        '2201' => ['Vendor Wallets (float)', 'liability'],
        '4101' => ['Platform Service Fee Revenue', 'revenue'],
        '4201' => ['Commission Revenue', 'revenue'],
        '4301' => ['Membership Revenue', 'revenue'],
        '4901' => ['Promotion Expense', 'expense'],
        '4902' => ['Payment Gateway Fee Expense', 'expense'],
        '4903' => ['Withdrawal Fee Revenue', 'revenue'],
    ];

    public function account(string $code): LedgerAccount
    {
        if (! isset(self::CHART[$code])) {
            throw new DomainException("Unknown ledger account code [{$code}].", 'UNKNOWN_ACCOUNT', 422);
        }

        [$name, $type] = self::CHART[$code];

        return LedgerAccount::firstOrCreate(['code' => $code], ['name' => $name, 'type' => $type]);
    }

    /**
     * Post a balanced transaction. entries: [code => ['debit' => int, 'credit' => int, 'memo' => ?]]
     */
    public function post(string $group, ?string $referenceType, ?int $referenceId, array $entries, string $description = ''): int
    {
        $debitTotal = 0;
        $creditTotal = 0;
        $rows = [];

        foreach ($entries as $code => $entry) {
            $debit = (int) ($entry['debit'] ?? 0);
            $credit = (int) ($entry['credit'] ?? 0);

            if ($debit === 0 && $credit === 0) {
                continue; // skip zero-value legs
            }

            if ($debit < 0 || $credit < 0 || ($debit > 0 && $credit > 0)) {
                throw new DomainException("Invalid entry for account [{$code}].", 'INVALID_ENTRY', 422);
            }

            $debitTotal += $debit;
            $creditTotal += $credit;
            $rows[] = ['code' => $code, 'debit' => $debit, 'credit' => $credit, 'memo' => $entry['memo'] ?? $description];
        }

        if ($debitTotal !== $creditTotal || $debitTotal === 0) {
            throw new DomainException(
                "Ledger transaction is unbalanced: debit {$debitTotal} != credit {$creditTotal}.",
                'UNBALANCED_TRANSACTION',
                422,
            );
        }

        return DB::transaction(function () use ($group, $referenceType, $referenceId, $description, $rows) {
            $txId = DB::table('ledger_transactions')->insertGetId([
                'group' => $group,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'created_at' => now(),
            ]);

            foreach ($rows as $row) {
                DB::table('ledger_entries')->insert([
                    'ledger_transaction_id' => $txId,
                    'ledger_account_id' => $this->account($row['code'])->id,
                    'debit' => $row['debit'],
                    'credit' => $row['credit'],
                    'memo' => $row['memo'],
                    'created_at' => now(),
                ]);
            }

            return $txId;
        });
    }

    /** Immutable correction: post full reversing transaction. */
    public function reverse(int $ledgerTransactionId, string $reason = ''): int
    {
        $tx = DB::table('ledger_transactions')->where('id', $ledgerTransactionId)->first();

        if (! $tx) {
            throw new DomainException('Ledger transaction not found.', 'NOT_FOUND', 404);
        }

        if ($tx->reversal_of !== null) {
            throw new DomainException('Cannot reverse a reversal.', 'DOUBLE_REVERSAL', 409);
        }

        $already = DB::table('ledger_transactions')->where('reversal_of', $ledgerTransactionId)->exists();
        if ($already) {
            throw new DomainException('Transaction already reversed.', 'ALREADY_REVERSED', 409);
        }

        $entries = DB::table('ledger_entries')->where('ledger_transaction_id', $ledgerTransactionId)->get();
        $codeById = LedgerAccount::pluck('code', 'id');

        $reversalEntries = [];
        foreach ($entries as $entry) {
            $reversalEntries[$codeById[$entry->ledger_account_id]] = [
                'debit' => (int) $entry->credit,
                'credit' => (int) $entry->debit,
                'memo' => "Reversal: {$reason}",
            ];
        }

        $reversalId = $this->post('adjustment', $tx->reference_type, $tx->reference_id, $reversalEntries, "Reversal of #{$ledgerTransactionId}: {$reason}");

        DB::table('ledger_transactions')->where('id', $reversalId)->update(['reversal_of' => $ledgerTransactionId]);

        return $reversalId;
    }

    /** Balance of an account (assets positive; liabilities/revenue by credit). */
    public function balance(string $code): int
    {
        $account = $this->account($code);

        $sums = DB::table('ledger_entries')->where('ledger_account_id', $account->id)
            ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')
            ->first();

        return (int) $sums->d - (int) $sums->c;
    }

    /** Global invariant: sum(debit) == sum(credit) across the whole ledger. */
    public function ledgerIsBalanced(): bool
    {
        $sums = DB::table('ledger_entries')
            ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')
            ->first();

        return (int) $sums->d === (int) $sums->c;
    }
}
