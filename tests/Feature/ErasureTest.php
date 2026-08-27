<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\Recommendations\Actions\ForgetSubject;
use Liberu\Ecommerce\Recommendations\Actions\RunGeneration;
use Liberu\Ecommerce\Recommendations\Actions\ServePlacement;
use Liberu\Ecommerce\Recommendations\Enums\Strategy;
use Liberu\Ecommerce\Recommendations\Events\SubjectForgotten;
use Liberu\Ecommerce\Recommendations\Models\Affinity;
use Liberu\Ecommerce\Recommendations\Models\Placement;
use Liberu\Ecommerce\Recommendations\Models\PlacementEntry;
use Liberu\Ecommerce\Recommendations\Models\Signal;
use Liberu\Ecommerce\Recommendations\Queries\ExportSubjectRecord;

beforeEach(function (): void {
    Config::set('recommendations.k_anonymity.minimum_subjects', 1);
});

function personWithHistory(string $subjectRef = 'person-1'): void
{
    order('tenant-a', $subjectRef, 'order-a', ['sku-1', 'sku-2']);
    order('tenant-a', 'person-other', 'order-c', ['sku-1', 'sku-5']);
    order('tenant-b', $subjectRef, 'order-b', ['sku-3', 'sku-4']);
    (new RunGeneration())('tenant-a', Strategy::Collaborative);
    (new ServePlacement())('tenant-a', 'related', 'sku-1', $subjectRef, 5);
}

it('exports everything it holds about a person, across every tenant', function (): void {
    personWithHistory();

    $record = (new ExportSubjectRecord())('person-1');

    expect($record->isEmpty())->toBeFalse()
        ->and($record->signals)->toHaveCount(4)
        ->and(array_column($record->signals, 'tenant_id'))->toBe(['tenant-a', 'tenant-a', 'tenant-b', 'tenant-b'])
        ->and($record->signals[0]['kind'])->toBe('purchase')
        ->and($record->signals[0]['group_ref'])->toBe('order-a')
        ->and($record->placements)->toHaveCount(1)
        ->and($record->placements[0]['slot'])->toBe('related')
        ->and($record->placements[0]['shown'])->toBe(['sku-5']);
});

it('erases the same set the export walks, and keeps the arithmetic', function (): void {
    Event::fake();
    personWithHistory();
    record('tenant-a', interaction(productRef: 'sku-1', sourceRef: 'other', subjectRef: 'person-9'));

    $affinitiesBefore = Affinity::query()->count();
    $report = (new ForgetSubject())('person-1');

    expect($report->signalsDeleted)->toBe(4)
        ->and($report->placementsDeleted)->toBe(1)
        ->and($report->affinitiesRetained)->toBe($affinitiesBefore)
        ->and(Affinity::query()->count())->toBe($affinitiesBefore)
        ->and(Signal::query()->where('subject_ref', 'person-1')->count())->toBe(0)
        ->and(Signal::query()->where('subject_ref', 'person-9')->count())->toBe(1)
        ->and(Placement::query()->where('subject_ref', 'person-1')->count())->toBe(0)
        ->and(PlacementEntry::query()->count())->toBe(0)
        ->and((new ExportSubjectRecord())('person-1')->isEmpty())->toBeTrue();

    Event::assertDispatched(SubjectForgotten::class, static fn (SubjectForgotten $e): bool => $e->subjectRef === 'person-1' && $e->signalsDeleted === 4);
});

it('leaves an anonymous signal alone, because there is nobody in it to forget', function (): void {
    record('tenant-a', interaction(subjectRef: '', sourceRef: 'e1'));

    expect((new ForgetSubject())('')->signalsDeleted)->toBe(0)
        ->and((new ExportSubjectRecord())('')->isEmpty())->toBeTrue()
        ->and(Signal::query()->count())->toBe(1);
});

it('erases a person from every merchant at once, because a person is not a merchant property', function (): void {
    personWithHistory();

    (new ForgetSubject())('person-1');

    expect(Signal::query()->where('tenant_id', 'tenant-b')->count())->toBe(0);
});
