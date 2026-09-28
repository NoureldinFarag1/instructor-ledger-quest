<?php

use App\Ledger\RecognizeEarnings;
use App\Models\Instructor;
use App\Models\LedgerEntry;
use App\Models\Subscription;
use Carbon\CarbonImmutable;

function subWithThreeInstructors(array $attrs = []): array
{
    $sub = Subscription::factory()->create($attrs); // 9,999 piastres, 70%, Sept 1 to Oct 1
    $instructors = Instructor::factory()->count(3)->create();
    $sub->instructors()->attach($instructors->mapWithKeys(fn ($i) => [$i->id => ['weight' => 1]]));

    return [$sub, $instructors];
}

function earnedFor(int $instructorId): int
{
    return (int) LedgerEntry::where('instructor_id', $instructorId)->sum('amount');
}

it('recognizes the running total on day 4', function () {
    [$sub, $instructors] = subWithThreeInstructors();

    app(RecognizeEarnings::class)->handle($sub, CarbonImmutable::parse('2026-09-04'));

    // pool 7,000 → day 4 earned 933 → 311 each
    $instructors->each(fn ($i) => expect(earnedFor($i->id))->toBe(311));
});

it('does not double count when run twice on the same day', function () {
    [$sub, $instructors] = subWithThreeInstructors();
    $action = app(RecognizeEarnings::class);

    $action->handle($sub, CarbonImmutable::parse('2026-09-04'));
    $action->handle($sub, CarbonImmutable::parse('2026-09-04'));

    expect(LedgerEntry::count())->toBe(3);
    $instructors->each(fn ($i) => expect(earnedFor($i->id))->toBe(311));
});

it('stops earning at the refund date', function () {
    [$sub, $instructors] = subWithThreeInstructors(['refunded_on' => '2026-09-10']);

    app(RecognizeEarnings::class)->handle($sub, CarbonImmutable::parse('2026-09-30'));

    // 10 days: 7,000 × 10 ÷ 30 = 2,333 → 777 each
    $instructors->each(fn ($i) => expect(earnedFor($i->id))->toBe(777));
});

it('lands exactly on the pool after the term, leftover to the platform', function () {
    [$sub, $instructors] = subWithThreeInstructors();

    app(RecognizeEarnings::class)->handle($sub, CarbonImmutable::parse('2026-10-15'));

    $instructors->each(fn ($i) => expect(earnedFor($i->id))->toBe(2333));
    expect((int) LedgerEntry::sum('amount'))->toBe(6999); // 1 piastre stays with the platform
});
