<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Queries;

use Liberu\Ecommerce\Recommendations\Data\Candidate;
use Liberu\Ecommerce\Recommendations\Data\Placement as PlacementData;
use Liberu\Ecommerce\Recommendations\Models\Placement;
use Liberu\Ecommerce\Recommendations\Models\PlacementEntry;
use Liberu\Ecommerce\Recommendations\Policies\CustodyPolicy;

/**
 * The stored placement, read back. This is what the placement table is for:
 * asking, months later, why a shopper was shown what they were shown.
 */
final class ExplainPlacement
{
    public function __invoke(string $tenantId, int $placementId): ?PlacementData
    {
        $placement = Placement::query()->find($placementId);

        if (! $placement instanceof Placement || ! CustodyPolicy::ownsPlacement($placement, $tenantId)) {
            return null;
        }

        $shown = [];
        $excluded = [];

        foreach ($placement->entries()->orderByRaw('position is null')->orderBy('position')->orderBy('product_ref')->get() as $entry) {
            $candidate = $this->candidate($entry);
            $entry->excluded_for === null ? $shown[] = $candidate : $excluded[] = $candidate;
        }

        return new PlacementData(
            $placement->tenant_id, $placement->slot, $placement->subject_ref, $placement->anchor_ref,
            $placement->requested, $shown, $excluded, $placement->candidates_examined,
            $placement->catalogue_checked, $placement->cart_checked, $placement->seed,
            $placement->refusal, $placement->id,
        );
    }

    private function candidate(PlacementEntry $entry): Candidate
    {
        return new Candidate(
            $entry->product_ref,
            $entry->strategy,
            (float) $entry->raw_score,
            (float) $entry->normalised_score,
            $entry->evidence_count,
            $entry->excluded_for,
        );
    }
}
