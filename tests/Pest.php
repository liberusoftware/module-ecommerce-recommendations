<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Liberu\Ecommerce\Recommendations\Actions\RecordSignal;
use Liberu\Ecommerce\Recommendations\Data\CatalogueItem;
use Liberu\Ecommerce\Recommendations\Data\Interaction;
use Liberu\Ecommerce\Recommendations\Enums\SignalKind;
use Liberu\Ecommerce\Recommendations\Tests\Fakes\FakeCatalogue;
use Liberu\Ecommerce\Recommendations\Tests\Fakes\FakeShopper;
use Liberu\Ecommerce\Recommendations\Tests\Fakes\FakeSignalSource;
use Liberu\PackageTestbench\PackageTestCase;

uses(PackageTestCase::class, RefreshDatabase::class)->in('Feature');
uses(PackageTestCase::class)->in('Unit');

/*
 * No test inherits a binding. Half of this suite claims something about an
 * unbound seam, which a leaked binding would quietly disprove.
 */
uses()->beforeEach(function (): void {
    Config::set('recommendations.seams.signal_source', null);
    Config::set('recommendations.seams.catalogue', null);
    Config::set('recommendations.seams.shopper', null);
    Config::set('recommendations.k_anonymity.minimum_subjects', 5);
    Config::set('recommendations.retention.signal_days', null);
    Config::set('recommendations.serve.candidate_overfetch', 3);
    Carbon::setTestNow(Carbon::parse('2026-08-27 12:00:00'));
})->in('Feature', 'Unit');

function interaction(
    string $productRef = 'sku-1',
    SignalKind $kind = SignalKind::View,
    string $sourceRef = 'event-1',
    string $subjectRef = 'person-1',
    string $groupRef = '',
    ?Carbon $at = null,
): Interaction {
    return new Interaction($productRef, $kind, $sourceRef, $at ?? Carbon::now(), $subjectRef, $groupRef);
}

function record(string $tenantId = 'tenant-a', ?Interaction $interaction = null): void
{
    (new RecordSignal())($tenantId, $interaction ?? interaction());
}

/** One order, one subject, several products — the shape co-purchase is counted in. */
function order(string $tenantId, string $subjectRef, string $orderRef, array $productRefs, ?Carbon $at = null): void
{
    foreach ($productRefs as $index => $productRef) {
        record($tenantId, interaction(
            productRef: $productRef,
            kind: SignalKind::Purchase,
            sourceRef: $orderRef.'-line-'.$index,
            subjectRef: $subjectRef,
            groupRef: $orderRef,
            at: $at,
        ));
    }
}

/** @param  array<int, string>  $subjects */
function ordersFrom(string $tenantId, array $subjects, array $productRefs): void
{
    foreach ($subjects as $index => $subject) {
        order($tenantId, $subject, 'order-'.$index, $productRefs);
    }
}

function bindCatalogue(array $items = []): FakeCatalogue
{
    $catalogue = new FakeCatalogue($items);
    Config::set('recommendations.seams.catalogue', $catalogue);

    return $catalogue;
}

function item(string $ref, bool $inStock = true, bool $suppressed = false, array $categories = [], array $tags = []): CatalogueItem
{
    return new CatalogueItem($ref, $inStock, $suppressed, $categories, $tags);
}

function bindShopper(array $cart = []): FakeShopper
{
    $shopper = new FakeShopper($cart);
    Config::set('recommendations.seams.shopper', $shopper);

    return $shopper;
}

function bindSignalSource(array $offers = []): FakeSignalSource
{
    $source = new FakeSignalSource($offers);
    Config::set('recommendations.seams.signal_source', $source);

    return $source;
}
