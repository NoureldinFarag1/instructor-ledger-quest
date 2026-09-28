# How I used AI

I used Claude for this whole task, in two very different ways. I want to be upfront about which parts are mine and which were generated, because I'd rather you trust what I say in the review than be impressed by the document.

## I started by learning the business, not writing code

Before writing anything, I asked Claude not to give me answers but to turn the problem into exercises I had to solve by hand. That was slower, and honestly a bit humbling.

My first answers were wrong. I split a payment in a way that didn't even add up to 100%, I gave the platform its whole cut on day one, and I assumed that rounding each day's earnings would land exactly on the total. Working the real numbers is what fixed that. Once I calculated that a 6,999 piastre pool over 30 days loses 9 piastres with daily rounding, I understood why the running total is necessary. Once I walked through a student refunding on day 45 after instructors had already been paid for the whole year, I understood why only earned money should ever be payable.

Those two ideas are the foundation of the whole design, and they're the parts I'm most confident explaining.

## Decisions that came from me

**Leftover piastres go to the platform.** This was my answer to the rounding exercise. It's deterministic, it still works on a perfect tie between instructors, and it's one rule applied in one place.

**The platform cut is rounded down, and the instructor pool is what remains.** I chose the direction of the rounding, and it means there's only one rounding step, so the cut and the pool always add back up to the payment.

The rest of the design (time based earning, the append only ledger, the payout state machine, the reconcile step) came out of working through the problem with Claude. I didn't invent those patterns, but I adopted them only after I could explain why each one prevents a specific failure.

## Then deadline pressure changed how I worked

I had about half a day, and by the time I understood the business, a lot of that was gone. From there, Claude generated most of the code, and my role shifted to reviewing it, running it, debugging it and asking about anything I couldn't explain.

**What I wrote myself:** The Migrations and the payout flow.

**What was generated and I reviewed and ran:** the models, the calculator, the recognition action, the tests, the Filament screen, and a first draft of the docs, which I then edited.

My main workflow was simple: ask for one piece at a time, run it immediately, paste back the exact error or output, and not move on until the tests passed and I understood the result.

## Where running the system mattered more than the tests

Two real bugs made it past a fully green test suite, and I only found them by actually running the system with realistic data.

The first time I seeded more than one subscription, the database rejected it. A unique index I had typed wrong meant each instructor could only ever belong to one subscription. Every test used a single subscription, so none of them could catch it.

The first real payout run failed on a typo in the mock provider's random path. The tests always force a specific outcome, so that path had never executed. It turned into a useful accidental demo, though: the crashed payouts were never resent, and the reconcile command released their money safely on its own.

Both taught me the same lesson: tests only prove the paths they actually exercise.

## Trade-offs I'm choosing on purpose

The platform always keeps the rounding crumbs, at most a piastre or two per split. It's slightly in the platform's favour, but it's simple, deterministic and easy to audit.

Engagement weights are static per subscription. That kept the scope honest for the time I had, and I've listed what breaks if they change mid-term.

Tests run on MySQL rather than SQLite, which is slower, but the double payment guarantees depend on real unique indexes, CHECK constraints and row locks, so testing against anything else would prove less.

## What I think sets this apart

Refunds never require clawing money back from instructors, because only earned money is ever paid. Recognition never drifts, and running it twice is harmless. Every guarantee against double payment is enforced twice, once in the code and once by the database itself. And the mock provider is deliberately not idempotent, so the tests prove that my system prevents double payment rather than quietly relying on the provider to do it.
