<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\Recommendations\Actions\RunGeneration;
use Liberu\Ecommerce\Recommendations\Enums\AffinityState;
use Liberu\Ecommerce\Recommendations\Enums\RefusalReason;
use Liberu\Ecommerce\Recommendations\Enums\RunState;
use Liberu\Ecommerce\Recommendations\Enums\Strategy;
use Liberu\Ecommerce\Recommendations\Events\AffinitiesGenerated;
use Liberu\Ecommerce\Recommendations\Models\Affinity;
use Liberu\Ecommerce\Recommendations\Models\AffinityEvent;
use Liberu\Ecommerce\Recommendations\Models\GenerationRun;

it('records a run rather than a side effect, and dispatches what it did', function (): void {
    Event::fake();
    Config::set('recommendations.k_anonymity.minimum_subjects', 2);
    ordersFrom('tenant-a', ['person-1', 'person-2'], ['sku-1', 'sku-2']);

    $report = (new RunGeneration())('tenant-a', Strategy::Collaborative);
    $run = GenerationRun::query()->findOrFail($report->runId);

    expect($report->succeeded())->toBeTrue()
        ->and($run->state)->toBe(RunState::Succeeded)
        ->and($run->strategy)->toBe(Strategy::Collaborative)
        ->and($run->window_days)->toBe(30)
        ->and($run->finished_at)->not->toBeNull()
        ->and($run->asserted)->toBe(2)
        ->and($run->candidates_in)->toBe(2)
        ->and($run->affinities()->count())->toBe(2);

    Event::assertDispatched(AffinitiesGenerated::class, static fn (AffinitiesGenerated $event): bool => $event->tenantId === 'tenant-a'
        && $event->strategy === Strategy::Collaborative
        && $event->asserted === 2);
});

it('scores a co-purchase as confidence, counted by occurrence rather than by line', function (): void {
    Config::set('recommendations.k_anonymity.minimum_subjects', 2);

    // Two shoppers bought both; a third bought sku-1 alone, and put it on the
    // order twice. Confidence is two of three orders, not two of four lines.
    ordersFrom('tenant-a', ['person-1', 'person-2'], ['sku-1', 'sku-2']);
    order('tenant-a', 'person-3', 'order-solo', ['sku-1', 'sku-1']);

    (new RunGeneration())('tenant-a', Strategy::Collaborative);

    $affinity = Affinity::query()->where('from_ref', 'sku-1')->where('to_ref', 'sku-2')->firstOrFail();

    expect($affinity->ratio())->toBe(round(2 / 3, 6))
        ->and($affinity->evidence_count)->toBe(2)
        ->and($affinity->subject_count)->toBe(2)
        ->and($affinity->state)->toBe(AffinityState::Active);
});

it('withholds a claim about people that too few of them stand behind', function (): void {
    Config::set('recommendations.k_anonymity.minimum_subjects', 3);
    ordersFrom('tenant-a', ['person-1', 'person-2'], ['sku-1', 'sku-2']);

    $report = (new RunGeneration())('tenant-a', Strategy::Collaborative);

    expect($report->candidatesIn)->toBe(2)
        ->and($report->asserted)->toBe(0)
        ->and($report->withheldBelowFloor)->toBe(2)
        ->and($report->kAnonymityFloor)->toBe(3)
        ->and(Affinity::query()->count())->toBe(0);
});

it('scores popularity as a share of the window shoppers, under no anchor', function (): void {
    Config::set('recommendations.k_anonymity.minimum_subjects', 1);
    record('tenant-a', interaction(productRef: 'sku-1', sourceRef: 'e1', subjectRef: 'person-1'));
    record('tenant-a', interaction(productRef: 'sku-1', sourceRef: 'e2', subjectRef: 'person-2'));
    record('tenant-a', interaction(productRef: 'sku-2', sourceRef: 'e3', subjectRef: 'person-1'));
    record('tenant-a', interaction(productRef: 'sku-2', sourceRef: 'e4', subjectRef: ''));

    (new RunGeneration())('tenant-a', Strategy::Popularity);

    $top = Affinity::query()->where('to_ref', 'sku-1')->firstOrFail();
    $next = Affinity::query()->where('to_ref', 'sku-2')->firstOrFail();

    expect($top->from_ref)->toBe(Affinity::ANCHORLESS)
        ->and($top->ratio())->toBe(1.0)
        ->and($next->ratio())->toBe(0.5)
        ->and($next->evidence_count)->toBe(2)
        ->and($next->subject_count)->toBe(1);
});

it('generates no popularity at all when nobody who is anybody was seen', function (): void {
    record('tenant-a', interaction(subjectRef: '', sourceRef: 'e1'));

    $report = (new RunGeneration())('tenant-a', Strategy::Popularity);

    expect($report->succeeded())->toBeTrue()
        ->and($report->candidatesIn)->toBe(0)
        ->and(Affinity::query()->count())->toBe(0);
});

it('fails a content-similarity run by name when no catalogue answers', function (): void {
    record('tenant-a', interaction(productRef: 'sku-1', sourceRef: 'e1'));
    record('tenant-a', interaction(productRef: 'sku-2', sourceRef: 'e2'));

    $report = (new RunGeneration())('tenant-a', Strategy::ContentSimilarity);

    expect($report->succeeded())->toBeFalse()
        ->and($report->state)->toBe(RunState::Failed)
        ->and($report->failure)->toBe(RefusalReason::NoCatalogueReaderBound)
        ->and(GenerationRun::query()->findOrFail($report->runId)->failure_reason)->toBe('no_catalogue_reader_bound')
        ->and(Affinity::query()->count())->toBe(0);
});

it('scores content similarity on classification overlap and applies no anonymity floor to it', function (): void {
    Config::set('recommendations.k_anonymity.minimum_subjects', 50);
    bindCatalogue([
        'sku-1' => item('sku-1', categories: ['kitchen'], tags: ['steel']),
        'sku-2' => item('sku-2', categories: ['kitchen'], tags: ['steel']),
        'sku-3' => item('sku-3', categories: ['garden']),
    ]);
    record('tenant-a', interaction(productRef: 'sku-1', sourceRef: 'e1'));
    record('tenant-a', interaction(productRef: 'sku-2', sourceRef: 'e2'));
    record('tenant-a', interaction(productRef: 'sku-3', sourceRef: 'e3'));

    $report = (new RunGeneration())('tenant-a', Strategy::ContentSimilarity);

    expect($report->succeeded())->toBeTrue()
        ->and($report->withheldBelowFloor)->toBe(0)
        ->and(Affinity::query()->where('from_ref', 'sku-1')->where('to_ref', 'sku-2')->firstOrFail()->ratio())->toBe(1.0)
        ->and(Affinity::query()->where('to_ref', 'sku-3')->exists())->toBeFalse();
});

it('computes nothing from a window that saw fewer than two products', function (): void {
    bindCatalogue(['sku-1' => item('sku-1', categories: ['kitchen'])]);
    record('tenant-a', interaction(productRef: 'sku-1', sourceRef: 'e1'));

    expect((new RunGeneration())('tenant-a', Strategy::ContentSimilarity)->candidatesIn)->toBe(0);
});

it('refuses to generate the strategy a merchandiser types by hand', function (): void {
    $report = (new RunGeneration())('tenant-a', Strategy::Manual);

    expect($report->failure)->toBe(RefusalReason::ManualIsNotGenerated)
        ->and($report->state)->toBe(RunState::Failed)
        ->and(GenerationRun::query()->findOrFail($report->runId)->finished_at)->not->toBeNull();
});

it('supersedes what the newest run did not reassert, with an audit row', function (): void {
    Config::set('recommendations.k_anonymity.minimum_subjects', 1);
    order('tenant-a', 'person-1', 'order-1', ['sku-1', 'sku-2'], Carbon::now()->subDays(20));

    (new RunGeneration())('tenant-a', Strategy::Collaborative);
    $affinity = Affinity::query()->where('to_ref', 'sku-2')->firstOrFail();

    expect($affinity->isActive())->toBeTrue();

    // A second run over a window that no longer contains the order.
    $report = (new RunGeneration())('tenant-a', Strategy::Collaborative, 5);

    expect($report->superseded)->toBe(2)
        ->and($affinity->refresh()->state)->toBe(AffinityState::Superseded)
        ->and($affinity->superseded_at)->not->toBeNull()
        ->and($affinity->events()->count())->toBe(2)
        ->and(AffinityEvent::query()->where('affinity_id', $affinity->id)->orderBy('sequence')->pluck('to_state')->all())
        ->toBe([AffinityState::Active, AffinityState::Superseded]);
});

it('brings a superseded claim back when the evidence returns', function (): void {
    Config::set('recommendations.k_anonymity.minimum_subjects', 1);
    order('tenant-a', 'person-1', 'order-1', ['sku-1', 'sku-2'], Carbon::now()->subDays(20));

    (new RunGeneration())('tenant-a', Strategy::Collaborative);
    (new RunGeneration())('tenant-a', Strategy::Collaborative, 5);
    (new RunGeneration())('tenant-a', Strategy::Collaborative, 30);

    $affinity = Affinity::query()->where('to_ref', 'sku-2')->firstOrFail();

    expect($affinity->state)->toBe(AffinityState::Active)
        ->and($affinity->superseded_at)->toBeNull()
        ->and($affinity->events()->count())->toBe(3);
});

it('leaves another strategy alone when one strategy retracts', function (): void {
    Config::set('recommendations.k_anonymity.minimum_subjects', 1);
    order('tenant-a', 'person-1', 'order-1', ['sku-1', 'sku-2'], Carbon::now()->subDays(20));
    record('tenant-a', interaction(productRef: 'sku-9', sourceRef: 'e9', subjectRef: 'person-1'));

    (new RunGeneration())('tenant-a', Strategy::Collaborative);
    (new RunGeneration())('tenant-a', Strategy::Popularity);
    (new RunGeneration())('tenant-a', Strategy::Collaborative, 5);

    expect(Affinity::query()->where('strategy', Strategy::Popularity->value)->where('state', 'active')->count())->toBeGreaterThan(0)
        ->and(Affinity::query()->where('strategy', Strategy::Collaborative->value)->where('state', 'active')->count())->toBe(0);
});
