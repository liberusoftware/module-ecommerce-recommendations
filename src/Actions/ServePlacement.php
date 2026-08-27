<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\Recommendations\Data\Candidate;
use Liberu\Ecommerce\Recommendations\Data\Placement as PlacementData;
use Liberu\Ecommerce\Recommendations\Events\PlacementServed;
use Liberu\Ecommerce\Recommendations\Models\Placement;
use Liberu\Ecommerce\Recommendations\Models\PlacementEntry;
use Liberu\Ecommerce\Recommendations\Queries\PlanPlacement;

/**
 * Persist before transmitting: the explanation the shopper sees and the one an
 * operator can audit are the same row. An empty placement is recorded too —
 * the refusals are the record of why the feature was quiet.
 */
final class ServePlacement
{
    public function __invoke(
        string $tenantId,
        string $slot,
        string $anchorRef = '',
        string $subjectRef = '',
        int $limit = 10,
        ?int $seed = null,
    ): PlacementData {
        $plan = (new PlanPlacement())($tenantId, $slot, $anchorRef, $subjectRef, $limit, $seed);

        $placement = DB::transaction(function () use ($plan): Placement {
            $placement = Placement::query()->create([
                'tenant_id' => $plan->tenantId,
                'slot' => $plan->slot,
                'subject_ref' => $plan->subjectRef,
                'anchor_ref' => $plan->anchorRef,
                'requested' => $plan->requested,
                'returned' => count($plan->shown),
                'candidates_examined' => $plan->candidatesExamined,
                'refusal' => $plan->refusal,
                'catalogue_checked' => $plan->catalogueChecked,
                'cart_checked' => $plan->cartChecked,
                'seed' => $plan->seed,
                'served_at' => Carbon::now(),
            ]);

            foreach ($plan->shown as $position => $candidate) {
                $this->entry($placement, $candidate, $position + 1);
            }

            foreach ($plan->excluded as $candidate) {
                $this->entry($placement, $candidate, null);
            }

            return $placement;
        });

        Event::dispatch(new PlacementServed(
            $plan->tenantId, $placement->id, $plan->slot, $plan->requested, count($plan->shown), $plan->refusal,
        ));

        return $plan->identified($placement->id);
    }

    private function entry(Placement $placement, Candidate $candidate, ?int $position): void
    {
        PlacementEntry::query()->create([
            'tenant_id' => $placement->tenant_id,
            'placement_id' => $placement->id,
            'product_ref' => $candidate->productRef,
            'strategy' => $candidate->strategy,
            'raw_score' => $candidate->rawScore,
            'normalised_score' => $candidate->normalisedScore,
            'evidence_count' => $candidate->evidenceCount,
            'position' => $position,
            'excluded_for' => $candidate->excludedFor,
        ]);
    }
}
