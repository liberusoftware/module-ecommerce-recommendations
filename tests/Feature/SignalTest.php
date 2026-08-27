<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Liberu\Ecommerce\Recommendations\Actions\RecordSignal;
use Liberu\Ecommerce\Recommendations\Enums\RefusalReason;
use Liberu\Ecommerce\Recommendations\Enums\SignalKind;
use Liberu\Ecommerce\Recommendations\Models\Signal;

it('records what the caller said happened and never asks a session who it was', function (): void {
    $outcome = (new RecordSignal())('tenant-a', interaction(subjectRef: '', sourceRef: 'event-9'));

    $signal = Signal::query()->findOrFail($outcome->id);

    expect($outcome->happened())->toBeTrue()
        ->and($signal->subject_ref)->toBe('')
        ->and($signal->hasSubject())->toBeFalse()
        ->and($signal->kind)->toBe(SignalKind::View)
        ->and($signal->retain_until)->toBeNull();
});

it('refuses a signal with no product and one with no cause', function (): void {
    expect((new RecordSignal())('tenant-a', interaction(productRef: ''))->reason)->toBe(RefusalReason::ProductReferenceRequired)
        ->and((new RecordSignal())('tenant-a', interaction(sourceRef: ''))->reason)->toBe(RefusalReason::ProductReferenceRequired)
        ->and(Signal::query()->count())->toBe(0);
});

it('lets the natural key arbitrate a repeat rather than reading first', function (): void {
    $first = (new RecordSignal())('tenant-a', interaction(sourceRef: 'event-1'));
    $again = (new RecordSignal())('tenant-a', interaction(sourceRef: 'event-1'));

    expect($first->happened())->toBeTrue()
        ->and($again->happened())->toBeFalse()
        ->and($again->id)->toBe($first->id)
        ->and(Signal::query()->count())->toBe(1);
});

it('gives a second person their own row for a deliberately identical reference', function (): void {
    $a = (new RecordSignal())('tenant-a', interaction(subjectRef: 'person-1', sourceRef: 'event-1'));
    $b = (new RecordSignal())('tenant-a', interaction(subjectRef: 'person-2', sourceRef: 'event-1'));

    expect($a->happened())->toBeTrue()
        ->and($b->happened())->toBeTrue()
        ->and($b->id)->not->toBe($a->id)
        ->and(Signal::query()->count())->toBe(2);
});

it('stamps a retention window on a subject-keyed signal and on no other', function (): void {
    Config::set('recommendations.retention.signal_days', 30);

    $keyed = (new RecordSignal())('tenant-a', interaction(subjectRef: 'person-1', sourceRef: 'event-1'));
    $anonymous = (new RecordSignal())('tenant-a', interaction(subjectRef: '', sourceRef: 'event-2'));

    expect(Signal::query()->findOrFail($keyed->id)->retain_until?->toDateString())->toBe('2026-09-26')
        ->and(Signal::query()->findOrFail($anonymous->id)->retain_until)->toBeNull();
});

it('does not swallow a write failure that is not the natural key', function (): void {
    Schema::table('recommendations_signals', function ($table): void {
        $table->unique('product_ref', 'one_signal_per_product');
    });

    record('tenant-a', interaction(sourceRef: 'event-1'));

    expect(fn () => (new RecordSignal())('tenant-a', interaction(sourceRef: 'event-2')))
        ->toThrow(QueryException::class);
});

it('keeps the occurrence a signal belongs to, so two lines of one order are one cause', function (): void {
    order('tenant-a', 'person-1', 'order-1', ['sku-1', 'sku-1', 'sku-2'], Carbon::now());

    expect(Signal::query()->where('group_ref', 'order-1')->count())->toBe(3)
        ->and(Signal::query()->where('group_ref', 'order-1')->distinct()->count('product_ref'))->toBe(2);
});
