<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Liberu\Ecommerce\Recommendations\Actions\PruneExpiredSignals;
use Liberu\Ecommerce\Recommendations\Enums\RefusalReason;
use Liberu\Ecommerce\Recommendations\Models\Signal;

it('refuses to prune on a window the host never configured, and says how many stand unbounded', function (): void {
    record('tenant-a', interaction(subjectRef: 'person-1', sourceRef: 'e1'));
    record('tenant-a', interaction(subjectRef: '', sourceRef: 'e2'));

    $report = (new PruneExpiredSignals())();

    expect($report->wasRefused())->toBeTrue()
        ->and($report->refusal)->toBe(RefusalReason::RetentionWindowUnknown)
        ->and($report->deleted)->toBe(0)
        ->and($report->unbounded)->toBe(1)
        ->and(Signal::query()->count())->toBe(2);
});

it('drops a subject-keyed signal once its window closes and keeps the rest', function (): void {
    Config::set('recommendations.retention.signal_days', 30);
    record('tenant-a', interaction(subjectRef: 'person-1', sourceRef: 'old', at: Carbon::now()->subDays(60)));
    record('tenant-a', interaction(subjectRef: 'person-1', sourceRef: 'new', at: Carbon::now()));
    record('tenant-a', interaction(subjectRef: '', sourceRef: 'anon', at: Carbon::now()->subYears(3)));

    $report = (new PruneExpiredSignals())();

    expect($report->wasRefused())->toBeFalse()
        ->and($report->deleted)->toBe(1)
        ->and($report->unbounded)->toBe(0)
        ->and(Signal::query()->orderBy('source_ref')->pluck('source_ref')->all())->toBe(['anon', 'new']);
});

it('prunes as at a moment the caller names', function (): void {
    Config::set('recommendations.retention.signal_days', 30);
    record('tenant-a', interaction(subjectRef: 'person-1', sourceRef: 'e1', at: Carbon::now()));

    expect((new PruneExpiredSignals())(Carbon::now()->addDays(10))->deleted)->toBe(0)
        ->and((new PruneExpiredSignals())(Carbon::now()->addDays(40))->deleted)->toBe(1);
});
