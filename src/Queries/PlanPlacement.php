<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Queries;

use Liberu\Ecommerce\Recommendations\Data\Candidate;
use Liberu\Ecommerce\Recommendations\Data\CatalogueItem;
use Liberu\Ecommerce\Recommendations\Data\Placement;
use Liberu\Ecommerce\Recommendations\Enums\AffinityState;
use Liberu\Ecommerce\Recommendations\Enums\ExclusionReason;
use Liberu\Ecommerce\Recommendations\Enums\RefusalReason;
use Liberu\Ecommerce\Recommendations\Enums\RunState;
use Liberu\Ecommerce\Recommendations\Enums\SignalKind;
use Liberu\Ecommerce\Recommendations\Models\Affinity;
use Liberu\Ecommerce\Recommendations\Models\GenerationRun;
use Liberu\Ecommerce\Recommendations\Models\Signal;
use Liberu\Ecommerce\Recommendations\Support\Cast;
use Liberu\Ecommerce\Recommendations\Support\Normalisation;
use Liberu\Ecommerce\Recommendations\Support\Ranking;
use Liberu\Ecommerce\Recommendations\Support\Seams;
use Liberu\Ecommerce\Recommendations\Support\Settings;

/**
 * The whole answer, computed and written nowhere. An empty plan carries the
 * precondition that produced it, because a recommender's failure mode is
 * silence and silence reads as an empty result.
 *
 * There is no implicit fallback from an anchored slot to popularity: the host's
 * fall through to trending is what made "this shopper is new", "nothing was
 * ever recorded" and "the generator never ran" one output. A surface that wants
 * popularity asks for it by leaving the anchor out.
 */
final class PlanPlacement
{
    public function __invoke(
        string $tenantId,
        string $slot,
        string $anchorRef = '',
        string $subjectRef = '',
        int $limit = 10,
        ?int $seed = null,
    ): Placement {
        $limit = max(1, $limit);
        $catalogue = Seams::catalogue();
        $shopper = Seams::shopper();
        $rows = $this->read($tenantId, $anchorRef, $limit * Settings::candidateOverfetch());

        $candidates = Normalisation::perStrategy(array_map(
            static fn (Affinity $affinity): Candidate => new Candidate(
                $affinity->to_ref, $affinity->strategy, $affinity->ratio(), 0.0, $affinity->evidence_count,
            ),
            $rows,
        ));

        $described = $catalogue === null
            ? []
            : $catalogue->describe($tenantId, array_map(static fn (Candidate $c): string => $c->productRef, $candidates));

        $purchased = $this->purchasedRefs($tenantId, $subjectRef);
        $inCart = $shopper !== null && $subjectRef !== '' ? $shopper->cartRefs($tenantId, $subjectRef) : [];

        $judged = array_map(
            fn (Candidate $candidate): Candidate => $this->judge($candidate, $anchorRef, $purchased, $inCart, $catalogue !== null, $described),
            $candidates,
        );

        $survivors = array_values(array_filter($judged, static fn (Candidate $c): bool => $c->survives()));
        $excluded = array_values(array_filter($judged, static fn (Candidate $c): bool => ! $c->survives()));
        $shown = array_slice(Ranking::order($survivors, $seed), 0, $limit);

        return new Placement(
            $tenantId, $slot, $subjectRef, $anchorRef, $limit, $shown, $excluded, count($candidates),
            $catalogue !== null, $shopper !== null && $subjectRef !== '', $seed,
            $shown === [] ? $this->refusal($tenantId, $candidates !== []) : null,
        );
    }

    /**
     * Ordered and limited in SQL. Manual first is part of the order rather than
     * a second pass, so the bound applies to the list the merchandiser meant.
     *
     * @return list<Affinity>
     */
    private function read(string $tenantId, string $anchorRef, int $take): array
    {
        return array_values(Affinity::query()
            ->where('tenant_id', $tenantId)
            ->where('from_ref', $anchorRef)
            ->where('state', AffinityState::Active->value)
            ->orderByRaw("case when strategy = 'manual' then 0 else 1 end")
            ->orderByDesc('score')
            ->orderBy('to_ref')
            ->limit($take)
            ->get()
            ->all());
    }

    /**
     * One list, one place, first reason wins. The host applied two different
     * exclusion sets in two services that never saw each other.
     *
     * @param  list<string>  $purchased
     * @param  list<string>  $inCart
     * @param  array<string, CatalogueItem>  $described
     */
    private function judge(Candidate $candidate, string $anchorRef, array $purchased, array $inCart, bool $catalogueBound, array $described): Candidate
    {
        $item = $described[$candidate->productRef] ?? null;

        $reason = match (true) {
            $candidate->productRef === $anchorRef && $anchorRef !== '' => ExclusionReason::IsAnchor,
            in_array($candidate->productRef, $purchased, true) => ExclusionReason::AlreadyPurchased,
            in_array($candidate->productRef, $inCart, true) => ExclusionReason::AlreadyInCart,

            // Unbound, the catalogue answers nothing and these three are simply
            // not applied; a ref is never dropped for having gone unchecked.
            ! $catalogueBound => null,
            $item === null => ExclusionReason::UnresolvableRef,
            $item->suppressed => ExclusionReason::Suppressed,
            ! $item->inStock => ExclusionReason::OutOfStock,
            default => null,
        };

        return $reason === null ? $candidate : $candidate->excludedFor($reason);
    }

    /** @return list<string> */
    private function purchasedRefs(string $tenantId, string $subjectRef): array
    {
        if ($subjectRef === '') {
            return [];
        }

        return array_values(Signal::query()
            ->where('tenant_id', $tenantId)
            ->where('subject_ref', $subjectRef)
            ->where('kind', SignalKind::Purchase->value)
            ->distinct()
            ->pluck('product_ref')
            ->map(static fn (mixed $ref): string => Cast::str($ref))
            ->all());
    }

    /** Three operational states the host could not tell apart, told apart. */
    private function refusal(string $tenantId, bool $hadCandidates): RefusalReason
    {
        if ($hadCandidates) {
            return RefusalReason::AllCandidatesExcluded;
        }

        if (Signal::query()->where('tenant_id', $tenantId)->doesntExist()) {
            return Seams::signalSource() === null
                ? RefusalReason::NoSignalSourceBound
                : RefusalReason::NoSignalsRecorded;
        }

        $ran = GenerationRun::query()
            ->where('tenant_id', $tenantId)
            ->where('state', RunState::Succeeded->value)
            ->exists();

        return $ran ? RefusalReason::NoAffinitiesForAnchor : RefusalReason::NoGenerationRun;
    }
}
