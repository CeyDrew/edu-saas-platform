<?php

use App\Domain\Learning\Sm2;

it('grows the interval 1, 6, then by easiness on good answers', function () {
    $state = ['easiness' => 2.5, 'interval' => 0, 'repetitions' => 0];
    $intervals = [];

    foreach (range(1, 4) as $_) {
        $state = Sm2::next($state['easiness'], $state['interval'], $state['repetitions'], 4);
        $intervals[] = $state['interval'];
    }

    expect($intervals)->toBe([1, 6, 15, 38])->and($state['easiness'])->toBe(2.5);
});

it('starts over after a wrong answer and lowers easiness', function () {
    $state = Sm2::next(2.5, 15, 3, 1);

    expect($state)->toBe(['easiness' => 1.96, 'interval' => 1, 'repetitions' => 0]);
});

it('never lets easiness fall below 1.3', function () {
    $easiness = 2.5;
    foreach (range(1, 20) as $_) {
        $easiness = Sm2::next($easiness, 1, 0, 0)['easiness'];
    }

    expect($easiness)->toBe(1.3);
});

it('rewards perfect recall with higher easiness', function () {
    expect(Sm2::next(2.5, 6, 2, 5)['easiness'])->toBe(2.6)
        ->and(Sm2::next(2.5, 6, 2, 3)['easiness'])->toBe(2.36);
});

it('maps answers to quality grades', function () {
    expect(Sm2::quality(false))->toBe(1)
        ->and(Sm2::quality(true))->toBe(4)
        ->and(Sm2::quality(true, 5))->toBe(5)
        ->and(Sm2::quality(true, 40))->toBe(3);
});
