<?php

use App\Planning\PillarQuota;

it('splits posts across pillars by share, largest remainder first', function () {
    expect(PillarQuota::split(['place' => 25, 'food_drink' => 25, 'people' => 15, 'guests_ugc' => 15, 'offers' => 20], 10))
        ->toBe(['place' => 3, 'food_drink' => 3, 'people' => 1, 'guests_ugc' => 1, 'offers' => 2]);
});

it('always sums to the requested count', function (int $count) {
    expect(array_sum(PillarQuota::split(['a' => 1, 'b' => 2, 'c' => 3], $count)))->toBe($count);
})->with([1, 2, 7, 30]);

it('rejects shares that add up to nothing', function () {
    PillarQuota::split([], 5);
})->throws(InvalidArgumentException::class);
