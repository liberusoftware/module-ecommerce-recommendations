<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Queries;

use Liberu\Ecommerce\Recommendations\Data\SubjectRecord;
use Liberu\Ecommerce\Recommendations\Models\Placement;
use Liberu\Ecommerce\Recommendations\Models\Signal;

/**
 * Everything this module holds about one person, across every tenant, in the
 * same set the erasure walks. Export and erasure disagreeing about what
 * "everything" means is how a profile survives a request to delete it.
 */
final class ExportSubjectRecord
{
    public function __invoke(string $subjectRef): SubjectRecord
    {
        if ($subjectRef === '') {
            return new SubjectRecord($subjectRef, [], []);
        }

        $signals = array_values(Signal::query()->where('subject_ref', $subjectRef)->orderBy('id')->get()
            ->map(static fn (Signal $signal): array => [
                'tenant_id' => $signal->tenant_id,
                'product_ref' => $signal->product_ref,
                'kind' => $signal->kind->value,
                'group_ref' => $signal->group_ref,
                'source_ref' => $signal->source_ref,
                'occurred_at' => $signal->occurred_at->toIso8601String(),
                'retain_until' => $signal->retain_until?->toIso8601String(),
            ])->all());

        $placements = array_values(Placement::query()->where('subject_ref', $subjectRef)->orderBy('id')->get()
            ->map(static fn (Placement $placement): array => [
                'tenant_id' => $placement->tenant_id,
                'slot' => $placement->slot,
                'anchor_ref' => $placement->anchor_ref,
                'requested' => $placement->requested,
                'returned' => $placement->returned,
                'refusal' => $placement->refusal?->value,
                'served_at' => $placement->served_at->toIso8601String(),
                'shown' => $placement->entries()->whereNotNull('position')->orderBy('position')->pluck('product_ref')->all(),
            ])->all());

        return new SubjectRecord($subjectRef, $signals, $placements);
    }
}
