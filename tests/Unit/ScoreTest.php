<?php

declare(strict_types=1);

use Liberu\Ecommerce\Recommendations\Exceptions\ScoreOutOfRange;
use Liberu\Ecommerce\Recommendations\Models\Affinity;
use Liberu\Ecommerce\Recommendations\Models\PlacementEntry;

it('stores a score as a ratio the schema can actually hold', function (): void {
    $affinity = new Affinity();
    $affinity->score = 0.5;

    expect($affinity->getAttributes()['score'])->toBe('0.500000')
        ->and($affinity->ratio())->toBe(0.5);
});

it('refuses the value the host wrote into a four-decimal column', function (float $score): void {
    $affinity = new Affinity();

    expect(fn () => $affinity->score = $score)->toThrow(ScoreOutOfRange::class);
})->with([85.5, -0.1, INF]);

it('holds a placement entry to the same range on both of its scores', function (): void {
    $entry = new PlacementEntry();
    $entry->raw_score = 1;
    $entry->normalised_score = '0.25';

    expect($entry->getAttributes()['raw_score'])->toBe('1.000000')
        ->and($entry->getAttributes()['normalised_score'])->toBe('0.250000')
        ->and(fn () => $entry->raw_score = 2.0)->toThrow(ScoreOutOfRange::class)
        ->and(fn () => $entry->normalised_score = 2.0)->toThrow(ScoreOutOfRange::class);
});
