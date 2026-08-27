<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Liberu\Ecommerce\Recommendations\Support\Settings;

it('reads a floor and an overfetch that can never be nothing', function (): void {
    expect(Settings::kAnonymityFloor())->toBe(5)
        ->and(Settings::candidateOverfetch())->toBe(3);

    Config::set('recommendations.k_anonymity.minimum_subjects', 0);
    Config::set('recommendations.serve.candidate_overfetch', 0);

    expect(Settings::kAnonymityFloor())->toBe(1)
        ->and(Settings::candidateOverfetch())->toBe(1);
});

it('treats an unconfigured retention window as nobody having said, not as zero', function (): void {
    expect(Settings::signalRetentionDays())->toBeNull();

    Config::set('recommendations.retention.signal_days', 0);
    expect(Settings::signalRetentionDays())->toBe(1);

    Config::set('recommendations.retention.signal_days', 90);
    expect(Settings::signalRetentionDays())->toBe(90);

    Config::set('recommendations.retention.signal_days', 'soon');
    expect(Settings::signalRetentionDays())->toBeNull();
});
