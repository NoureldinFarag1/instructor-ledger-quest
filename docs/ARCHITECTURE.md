# Architecture

## The core idea

Money is only payable once it's earned, and it's earned gradually over the subscription term. Almost every other decision follows from that one rule.

## Money storage

Every amount is an integer number of piastres in a `BIGINT` column. There are no floats or decimals anywhere in the money path, and every division uses `intdiv()`. Filament divides by 100 only for display.

## When money counts as earned

A student pays the whole term up front, but on day one the platform hasn't delivered anything yet. So the payment is recognized day by day across the term, like a gym membership.

Earning is driven by **time**, not by watching. A student who pays for a month and never opens a video has still been given a month of access, so that money is still earned. Engagement only decides **who** gets the instructor share, never **whether** it's earned.

This is what makes refunds safe. When a student leaves mid-term, they get back only the unearned part. Instructors keep what they earned up to the refund day, and because we only ever pay out earned money, nothing that was paid ever needs to be clawed back.

## How a payment is divided

1. **Platform cut first.** The platform cut is `intdiv(amount × (10000 − share_bps), 10000)`, rounded down, and the instructor pool is whatever remains. Because there's only one rounding step, cut plus pool always equals the payment exactly. The share is stored on each subscription in basis points, so if the platform changes its cut later, existing subscriptions keep the terms they were sold with.
2. **Recognition over time.** Earned to date is `intdiv(pool × elapsed_days, term_days)`, with elapsed clamped to the term.
3. **Split by engagement weight.** Each instructor gets `intdiv(earned × weight, total_weight)`.

## Rounding

The naive approach, computing each day's amount and rounding it, loses money. A 6,999 piastre pool over 30 days is 233.3 per day. Rounding every day down gives 233 × 30 = 6,990, and 9 piastres silently vanish.

Instead I always compute the **running total** (earned from the start up to today) and write only the difference from what's already recorded. Fractions carry forward instead of being thrown away. Most days credit 233, some days credit 234, and day 30 lands on exactly 6,999.

Shares are rounded down, so they can never add up to more than the total. The leftover is always smaller than the number of instructors, and it stays with the platform. That's a deliberate trade-off: the platform always keeps the crumbs, at most a piastre or two per split. It's deterministic, it works on a perfect tie, and it's easy to explain.

## The ledger

`ledger_entries` is append only. Nothing is ever updated or deleted, and corrections are new entries. Each row has an instructor, a signed amount and a type:

| Type | Sign | Meaning |
|---|---|---|
| `earned` | + | recognized earnings from a subscription |
| `payout_reserved` | − | money set aside for a payout |
| `payout_released` | + | a failed payout's money returned to the balance |

An instructor's outstanding balance is simply the sum of their entries. There's no balance column that could drift out of sync. Paid is the sum of succeeded payouts, and in flight is the sum of pending, processing and unknown payouts.

No table uses soft deletes. On a ledger, a soft delete would silently change balances while the row still sits there. Foreign keys restrict deletes, so the database also refuses to delete a subscription or instructor that has money attached.

## Recognition

`RecognizeEarnings` handles one subscription as of a date, inside a transaction with the subscription row locked. It computes each instructor's **target** earned to date and inserts only `target − already_recorded` when that's positive.

This makes recognition idempotent by design. A second run on the same day finds a difference of zero and writes nothing. Targets can only grow over time, because the earned pool only grows and rounding a bigger number down never gives a smaller share, so the difference is never negative.

## Idempotency: defense in layers

I didn't want correctness to depend on one mechanism, so each guarantee is enforced twice, once in code and once by the database.

1. **Payout creation.** Each payout gets a key of `payout:{instructor}:{date}`, with a unique index. Creation happens with the instructor row locked, so two servers running the payout at the same moment are serialized, and the second one sees the key already exists.
2. **Job execution.** The job first claims the payout inside a locked transaction, and only if it's `pending`, moving it to `processing` **before** calling the provider. A retried, duplicated or concurrent job finds it already claimed and exits without sending anything.
3. **Ledger writes.** A unique index on `(payout_id, type)` means a payout can be reserved once and released once, never twice. A unique index on `(instructor_id, subscription_id, recognized_through)` blocks two recognition runs from crediting the same day twice.
4. **Row shape.** CHECK constraints make invalid rows impossible: an earning can't be negative, a reservation can't be positive, a payout entry can't point at a subscription, and a payout's status must be one of five known values.

## The payout state machine

```
pending ──claim──▶ processing ──success──▶ succeeded
                        │
                        ├──failure──▶ failed (money released)
                        │
                        └──timeout──▶ unknown ──reconcile──▶ succeeded or failed
```

The payout command reserves each instructor's full outstanding balance with a negative ledger entry and creates the payout in the same transaction. The job is dispatched only after that commits.

## Provider timeout handling

When the provider times out, we genuinely don't know whether money moved. Resending would risk paying twice, and assuming failure would risk releasing money that was actually paid. So a timeout moves the payout to `unknown`, and **nothing ever resends it**.

`ledger:reconcile` asks the provider for the status of that key:

1. If the provider reports success, the payout becomes `succeeded`.
2. If it reports failure, or has no record of the key at all, the payout becomes `failed` and the reserved money is released back to the balance, ready for the next payout run.

The same command also rescues payouts stuck in `processing` (a worker that died mid-call) and re-dispatches `pending` payouts whose job was lost before it ran.

The mock provider is deliberately **not** idempotent: every successful send moves money again. That way the tests prove our system prevents double payment by itself, instead of quietly relying on the provider. In production I'd also pass the idempotency key to a real provider that supports one, as a second line of defense.

## Scaling considerations

At 500,000 active subscriptions:

1. Recognition currently walks every started subscription with `chunkById`. Next steps would be tracking a fully recognized marker on each subscription so finished ones are skipped, and fanning chunks out to queued jobs.
2. Recognizing daily creates a lot of rows (roughly subscriptions × instructors per run). Running recognition weekly or monthly cuts that sharply without changing any logic, because the running total works at any cadence.
3. `ledger_entries` will reach tens of millions of rows. It's indexed on `(instructor_id, type)` for balance sums, and it's a natural candidate for partitioning by month.
4. Summing the ledger per instructor is fine now but won't be forever. A balances table updated in the same transaction as each ledger write would make reads constant time while the ledger stays the source of truth.
5. The Filament screen runs a few queries per row. That's fine for an admin demo and would move to the balances table above.

## Known limitations

1. Engagement weights are static. If a weight changed mid-term, an instructor's target could drop, and the current design would simply skip the negative difference.
2. Treating "not found at provider" as a failure assumes the provider's status endpoint is immediately consistent. With a real provider I'd wait a grace period before releasing the money.
3. The mock provider stores its state in Laravel's cache, which is fine for a demo but obviously not durable.
4. The student's refund is calculated but not executed as a money movement.
5. There's no audit of who triggered a manual payout run.

## Senior bonus: changing plans mid-term

I'd treat an upgrade as two things the system already knows how to do.

First, the old subscription is ended on the change date exactly like a refund. Recognition stops there, instructors keep everything earned up to that day, and the unearned remainder is calculated with the same running total formula.

Second, instead of returning that remainder to the student, it becomes a credit toward a new subscription that starts on the change date. The student pays the new plan's price minus the credit, and the new subscription recognizes normally from day one.

Nothing already earned or paid is touched, so there's still nothing to claw back. The edge case is a downgrade where the credit exceeds the new price, and there the platform chooses between refunding the difference and carrying it forward as a balance. I'd want a product decision on that rather than guessing.
