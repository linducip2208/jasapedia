<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
    public $timestamps = false;

    protected $fillable = ['ledger_transaction_id', 'ledger_account_id', 'debit', 'credit', 'memo', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
