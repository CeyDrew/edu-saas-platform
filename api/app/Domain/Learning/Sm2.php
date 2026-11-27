<?php

namespace App\Domain\Learning;

/**
 * Simplified SM-2 spaced repetition (SuperMemo 2).
 *
 * Quality is graded 0..5. Below 3 the card starts over with a 1 day interval; otherwise
 * the interval grows 1 → 6 → interval × easiness. Easiness never drops below 1.3.
 */
final class Sm2
{
    public const float MIN_EASINESS = 1.3;

    /**
     * @return array{easiness: float, interval: int, repetitions: int}
     */
    public static function next(float $easiness, int $interval, int $repetitions, int $quality): array
    {
        $quality = max(0, min(5, $quality));

        if ($quality < 3) {
            $repetitions = 0;
            $interval = 1;
        } else {
            $repetitions++;
            $interval = match ($repetitions) {
                1 => 1,
                2 => 6,
                default => (int) round($interval * $easiness),
            };
        }

        $easiness += 0.1 - (5 - $quality) * (0.08 + (5 - $quality) * 0.02);

        return [
            'easiness' => round(max(self::MIN_EASINESS, $easiness), 2),
            'interval' => $interval,
            'repetitions' => $repetitions,
        ];
    }

    /**
     * Map a review answer to an SM-2 quality grade. A quick, confident correct answer
     * is "perfect", a slow one is "correct with hesitation".
     */
    public static function quality(bool $correct, ?int $secondsTaken = null): int
    {
        if (! $correct) {
            return 1;
        }

        return $secondsTaken !== null && $secondsTaken > 20 ? 3 : ($secondsTaken !== null && $secondsTaken <= 8 ? 5 : 4);
    }
}
