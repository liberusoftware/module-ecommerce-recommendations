<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Liberu\Ecommerce\Recommendations\Actions\IngestSignals;
use Liberu\Ecommerce\Recommendations\Contracts\CatalogueReader;
use Liberu\Ecommerce\Recommendations\Contracts\ShopperContext;
use Liberu\Ecommerce\Recommendations\Contracts\SignalSource;
use Liberu\Ecommerce\Recommendations\Enums\RefusalReason;
use Liberu\Ecommerce\Recommendations\Models\Signal;
use Liberu\Ecommerce\Recommendations\Support\Seams;
use Liberu\Ecommerce\Recommendations\Tests\Fakes\FakeCatalogue;
use Liberu\Ecommerce\Recommendations\Tests\Fakes\FakeShopper;
use Liberu\Ecommerce\Recommendations\Tests\Fakes\FakeSignalSource;

it('binds nothing by default, so nobody answered is a different answer from nothing', function (): void {
    expect(Seams::signalSource())->toBeNull()
        ->and(Seams::catalogue())->toBeNull()
        ->and(Seams::shopper())->toBeNull();
});

it('takes an instance, a class name or a container binding, and resolves at the moment of use', function (): void {
    Config::set('recommendations.seams.catalogue', new FakeCatalogue());
    expect(Seams::catalogue())->toBeInstanceOf(FakeCatalogue::class);

    Config::set('recommendations.seams.shopper', FakeShopper::class);
    expect(Seams::shopper())->toBeInstanceOf(FakeShopper::class);

    App::bind(SignalSource::class, static fn (): FakeSignalSource => new FakeSignalSource());
    expect(Seams::signalSource())->toBeInstanceOf(FakeSignalSource::class);
});

it('answers null rather than a wrong object when what is bound is not the contract', function (): void {
    Config::set('recommendations.seams.catalogue', stdClass::class);
    expect(Seams::catalogue())->toBeNull();

    App::bind(ShopperContext::class, static fn (): stdClass => new stdClass());
    expect(Seams::shopper())->toBeNull();

    App::bind(CatalogueReader::class, static fn (): FakeCatalogue => new FakeCatalogue());
    Config::set('recommendations.seams.catalogue', null);
    expect(Seams::catalogue())->toBeInstanceOf(FakeCatalogue::class);
});

it('ingests nothing and names the missing seam rather than inventing tracking', function (): void {
    $report = (new IngestSignals())('tenant-a', Carbon::now()->subDay(), Carbon::now());

    expect($report->wasRefused())->toBeTrue()
        ->and($report->refusal)->toBe(RefusalReason::NoSignalSourceBound)
        ->and($report->offered)->toBe(0)
        ->and(Signal::query()->count())->toBe(0);
});

it('counts what an offer produced, repeat and refusal apart from record', function (): void {
    $source = bindSignalSource([
        interaction(sourceRef: 'event-1'),
        interaction(sourceRef: 'event-1'),
        interaction(productRef: '', sourceRef: 'event-3'),
        interaction(sourceRef: 'event-4'),
    ]);

    $report = (new IngestSignals())('tenant-a', Carbon::now()->subDay(), Carbon::now());

    expect($report->offered)->toBe(4)
        ->and($report->recorded)->toBe(2)
        ->and($report->alreadyRecorded)->toBe(1)
        ->and($report->refusedRefs)->toBe(1)
        ->and($report->wasRefused())->toBeFalse()
        ->and($source->asked)->toBe(1)
        ->and($source->sawSince?->toDateString())->toBe('2026-08-26')
        ->and(Signal::query()->count())->toBe(2);
});
