<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Queries;

use Liberu\Ecommerce\Recommendations\Enums\AffinityState;
use Liberu\Ecommerce\Recommendations\Enums\Strategy;
use Liberu\Ecommerce\Recommendations\Models\Affinity;

/** What a merchant currently claims, for the operator rather than the shopper. */
final class ListAffinities
{
    /** @return list<Affinity> */
    public function __invoke(string $tenantId, string $anchorRef = '', ?Strategy $strategy = null, bool $activeOnly = true): array
    {
        return array_values(Affinity::query()
            ->where('tenant_id', $tenantId)
            ->where('from_ref', $anchorRef)
            ->when($strategy !== null, static fn ($query) => $query->where('strategy', $strategy?->value))
            ->when($activeOnly, static fn ($query) => $query->where('state', AffinityState::Active->value))
            ->orderByDesc('score')
            ->orderBy('to_ref')
            ->get()
            ->all());
    }
}
