<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerEntry extends Model
{
    const UPDATED_AT = null;

    public const TYPE_EARNED = 'earned';

    public const TYPE_PAYOUT_RESERVED = 'payout_reserved';

    public const TYPE_PAYOUT_RELEASED = 'payout_released';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'recognized_through' => 'immutable_date',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }
}
