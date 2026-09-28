<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'instructor_share_bps' => 'integer',
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'refunded_on' => 'immutable_date',
        ];
    }

    public function instructors(): BelongsToMany
    {
        return $this->belongsToMany(Instructor::class, 'subscription_instructor')
            ->withPivot('weight')
            ->withTimestamps();
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }
}
