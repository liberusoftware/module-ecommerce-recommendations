<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Liberu\Ecommerce\Recommendations\Data\Candidate;
use Liberu\Ecommerce\Recommendations\Data\ForgetReport;
use Liberu\Ecommerce\Recommendations\Data\IngestReport;
use Liberu\Ecommerce\Recommendations\Data\Outcome;
use Liberu\Ecommerce\Recommendations\Data\Placement;
use Liberu\Ecommerce\Recommendations\Data\PruneReport;
use Liberu\Ecommerce\Recommendations\Data\RunReport;
use Liberu\Ecommerce\Recommendations\Data\SubjectRecord;
use Liberu\Ecommerce\Recommendations\Enums\ExclusionReason;
use Liberu\Ecommerce\Recommendations\Enums\RefusalReason;
use Liberu\Ecommerce\Recommendations\Enums\RunState;
use Liberu\Ecommerce\Recommendations\Enums\SignalKind;
use Liberu\Ecommerce\Recommendations\Enums\Strategy;

it('distinguishes a refusal from a success and from a repeat', function (): void {
    expect(Outcome::recorded(7)->happened())->toBeTrue()
        ->and(Outcome::recorded(7)->id)->toBe(7)
        ->and(Outcome::alreadyRecorded(7)->happened())->toBeFalse()
        ->and(Outcome::alreadyRecorded(7)->wasRefused())->toBeFalse()
        ->and(Outcome::refused(RefusalReason::AnchorRequired)->wasRefused())->toBeTrue()
        ->and(Outcome::refused(RefusalReason::AnchorRequired)->reason)->toBe(RefusalReason::AnchorRequired);
});

it('carries the subject a caller stated and never invents one', function (): void {
    expect(interaction(subjectRef: 'person-1')->hasSubject())->toBeTrue()
        ->and(interaction(subjectRef: '')->hasSubject())->toBeFalse()
        ->and(interaction(kind: SignalKind::Purchase)->kind)->toBe(SignalKind::Purchase);
});

it('spells a catalogue item as a set of traits with the two kinds kept apart', function (): void {
    expect(item('sku-1', categories: ['c1'], tags: ['t1', 't1'])->traits())->toBe(['category:c1', 'tag:t1'])
        ->and(item('sku-1')->traits())->toBe([]);
});

it('reshapes a candidate without losing what came before', function (): void {
    $candidate = new Candidate('sku-1', Strategy::Popularity, 0.4, 0.0, 3);

    expect($candidate->survives())->toBeTrue()
        ->and($candidate->normalisedTo(0.8)->normalisedScore)->toBe(0.8)
        ->and($candidate->normalisedTo(0.8)->rawScore)->toBe(0.4)
        ->and($candidate->excludedFor(ExclusionReason::OutOfStock)->survives())->toBeFalse()
        ->and($candidate->excludedFor(ExclusionReason::OutOfStock)->excludedFor)->toBe(ExclusionReason::OutOfStock)
        ->and($candidate->excludedFor(ExclusionReason::OutOfStock)->normalisedTo(0.1)->excludedFor)->toBe(ExclusionReason::OutOfStock);
});

it('counts what each strategy contributed and what each exclusion removed', function (): void {
    $placement = new Placement(
        'tenant-a', 'related', 'person-1', 'sku-1', 4,
        [new Candidate('sku-2', Strategy::Manual, 1.0, 1.0, 1), new Candidate('sku-3', Strategy::Popularity, 0.5, 1.0, 9)],
        [new Candidate('sku-4', Strategy::Popularity, 0.2, 0.4, 2, ExclusionReason::OutOfStock)],
        3, true, true,
    );

    expect($placement->isEmpty())->toBeFalse()
        ->and($placement->productRefs())->toBe(['sku-2', 'sku-3'])
        ->and($placement->strategyCounts())->toBe(['manual' => 1, 'popularity' => 1])
        ->and($placement->exclusionCounts())->toBe(['out_of_stock' => 1])
        ->and($placement->identified(11)->id)->toBe(11)
        ->and($placement->identified(11)->shown)->toHaveCount(2);
});

it('treats a candidate excluded for no stated reason as unresolvable rather than shown', function (): void {
    $placement = new Placement('tenant-a', 'related', '', '', 1, [], [new Candidate('sku-9', Strategy::Manual, 1.0, 1.0, 1)], 1, false, false);

    expect($placement->isEmpty())->toBeTrue()
        ->and($placement->exclusionCounts())->toBe(['unresolvable_ref' => 1]);
});

it('reports what a run, an ingest, a prune and an erasure did', function (): void {
    expect((new RunReport(1, 'tenant-a', Strategy::Popularity, RunState::Succeeded, 30, 5, 4, 1, 0, 5))->succeeded())->toBeTrue()
        ->and((new RunReport(1, 'tenant-a', Strategy::Manual, RunState::Failed, 30, 0, 0, 0, 0, 5, RefusalReason::ManualIsNotGenerated))->succeeded())->toBeFalse()
        ->and((new IngestReport('tenant-a', 3, 2, 1))->wasRefused())->toBeFalse()
        ->and((new IngestReport('tenant-a', refusal: RefusalReason::NoSignalSourceBound))->wasRefused())->toBeTrue()
        ->and((new PruneReport(4))->wasRefused())->toBeFalse()
        ->and((new PruneReport(0, 2, RefusalReason::RetentionWindowUnknown))->wasRefused())->toBeTrue()
        ->and((new ForgetReport('person-1', 3, 1, 9))->affinitiesRetained)->toBe(9);
});

it('knows when it holds nothing about a person', function (): void {
    expect((new SubjectRecord('person-1', [], []))->isEmpty())->toBeTrue()
        ->and((new SubjectRecord('person-1', [['kind' => 'view']], []))->isEmpty())->toBeFalse();

    expect(Carbon::now()->toDateString())->toBe('2026-08-27');
});
