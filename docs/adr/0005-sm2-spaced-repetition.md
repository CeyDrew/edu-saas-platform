# ADR 0005: SM-2 for personal review

## Status
Accepted

## Context
Students should see the questions they got wrong again, at the moment they are about to
forget them, without tuning anything themselves. Options: a fixed schedule (1, 3, 7 days),
SM-2 (SuperMemo 2), or a newer model such as FSRS.

## Decision
Use a simplified SM-2 (`App\Domain\Learning\Sm2`):

- A wrong answer anywhere (video task, quiz) creates or resets a `review_items` row and makes
  it due tomorrow. This row doubles as the wrong-answer archive.
- During review the answer is graded 0–5: wrong = 1, correct = 4, quick correct (≤ 8 s) = 5,
  slow correct (> 20 s) = 3.
- Quality < 3 resets repetitions and the interval to 1 day; otherwise the interval goes
  1 → 6 → interval × easiness. Easiness changes by
  `0.1 − (5 − q) × (0.08 + (5 − q) × 0.02)` and never drops below 1.3.
- Items with an interval of 21+ days are shown as learned in the archive.

## Consequences
- The algorithm is a pure function with unit tests, independent of storage.
- State is three numbers per student and question, cheap to store and to query (`due_at` index).
- FSRS would schedule better but needs per-user parameter fitting and much more history than a
  single brand's students produce at the start; the `Sm2` class can be swapped later.
