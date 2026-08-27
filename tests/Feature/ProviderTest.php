<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Liberu\Ecommerce\Recommendations\RecommendationsServiceProvider;

it('boots nothing beyond its own config and migrations', function (): void {
    expect($this->app->getProvider(RecommendationsServiceProvider::class))->not->toBeNull()
        ->and(Config::get('recommendations.k_anonymity.minimum_subjects'))->not->toBeNull();

    foreach ([
        'recommendations_signals',
        'recommendations_generation_runs',
        'recommendations_affinities',
        'recommendations_affinity_events',
        'recommendations_placements',
        'recommendations_placement_entries',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
});

it('ships a config that binds no seam and assumes no retention window', function (): void {
    $shipped = require __DIR__.'/../../config/recommendations.php';

    expect($shipped['seams'])->toBe(['signal_source' => null, 'catalogue' => null, 'shopper' => null])
        ->and($shipped['retention']['signal_days'])->toBeNull()
        ->and($shipped['k_anonymity']['minimum_subjects'])->toBe(5);
});
