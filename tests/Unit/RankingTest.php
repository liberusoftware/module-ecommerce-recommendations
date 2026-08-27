<?php

declare(strict_types=1);

use Liberu\Ecommerce\Recommendations\Data\Candidate;
use Liberu\Ecommerce\Recommendations\Enums\Strategy;
use Liberu\Ecommerce\Recommendations\Support\Normalisation;
use Liberu\Ecommerce\Recommendations\Support\Ranking;

function candidate(string $ref, Strategy $strategy = Strategy::Popularity, float $raw = 0.5, float $normalised = 0.5): Candidate
{
    return new Candidate($ref, $strategy, $raw, $normalised, 1);
}

it('normalises each strategy against the candidate set actually read', function (): void {
    $normalised = Normalisation::perStrategy([
        candidate('sku-1', Strategy::Popularity, 0.2, 0.0),
        candidate('sku-2', Strategy::Popularity, 0.4, 0.0),
        candidate('sku-3', Strategy::Collaborative, 0.1, 0.0),
    ]);

    expect($normalised[0]->normalisedScore)->toBe(0.5)
        ->and($normalised[1]->normalisedScore)->toBe(1.0)
        ->and($normalised[2]->normalisedScore)->toBe(1.0)
        ->and($normalised[2]->rawScore)->toBe(0.1);
});

it('answers zero rather than one for a strategy with no scale to normalise against', function (): void {
    $normalised = Normalisation::perStrategy([candidate('sku-1', Strategy::Popularity, 0.0, 0.0)]);

    expect($normalised[0]->normalisedScore)->toBe(0.0);
});

it('puts a curated claim above a computed one of any score', function (): void {
    $ordered = Ranking::order([
        candidate('sku-1', Strategy::Popularity, 1.0, 1.0),
        candidate('sku-2', Strategy::Manual, 0.1, 0.1),
    ]);

    expect(array_map(static fn (Candidate $c): string => $c->productRef, $ordered))->toBe(['sku-2', 'sku-1']);
});

it('breaks a tie the same way every time when nobody asked for variety', function (): void {
    $candidates = [candidate('sku-b'), candidate('sku-c'), candidate('sku-a')];

    expect(array_map(static fn (Candidate $c): string => $c->productRef, Ranking::order($candidates)))
        ->toBe(['sku-a', 'sku-b', 'sku-c'])
        ->and(array_map(static fn (Candidate $c): string => $c->productRef, Ranking::order($candidates)))
        ->toBe(['sku-a', 'sku-b', 'sku-c']);
});

it('varies a tie by the seed it was given and by nothing else', function (): void {
    $candidates = [candidate('sku-a'), candidate('sku-b'), candidate('sku-c'), candidate('sku-d')];

    $first = array_map(static fn (Candidate $c): string => $c->productRef, Ranking::order($candidates, 11));
    $again = array_map(static fn (Candidate $c): string => $c->productRef, Ranking::order($candidates, 11));
    $other = array_map(static fn (Candidate $c): string => $c->productRef, Ranking::order($candidates, 22));

    expect($first)->toBe($again)
        ->and($first)->not->toBe($other)
        ->and(array_values(array_diff($first, $other)))->toBe([]);
});
