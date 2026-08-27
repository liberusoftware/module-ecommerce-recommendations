<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Support;

use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Liberu\Ecommerce\Recommendations\Data\Claim;
use Liberu\Ecommerce\Recommendations\Enums\SignalKind;
use Liberu\Ecommerce\Recommendations\Models\Signal;

/**
 * Co-purchase, counted by occurrence rather than by line: two lines of one
 * order are one piece of evidence. The score is confidence — of the orders
 * containing A, the share that also contained B — and not a frequency divided
 * by a number somebody assumed.
 */
final class CollaborativeClaims
{
    /** @return list<Claim> */
    public static function for(string $tenantId, Carbon $since, Carbon $until): array
    {
        $support = self::support($tenantId, $since, $until);

        if ($support === []) {
            return [];
        }

        $claims = [];

        foreach (self::pairs($tenantId, $since, $until) as $row) {
            $from = Cast::str($row->from_ref);
            $groups = Cast::int($row->groups);
            $anchorSupport = max(1, $support[$from] ?? 0);

            $claims[] = new Claim($from, Cast::str($row->to_ref), min(1.0, $groups / $anchorSupport), $groups, Cast::int($row->subjects));
        }

        return $claims;
    }

    /**
     * How many distinct occurrences contained each product.
     *
     * @return array<string, int>
     */
    private static function support(string $tenantId, Carbon $since, Carbon $until): array
    {
        $rows = Windows::signals($tenantId, $since, $until)
            ->where('kind', SignalKind::Purchase->value)
            ->where('group_ref', '!=', '')
            ->selectRaw('product_ref, COUNT(DISTINCT group_ref) as groups')
            ->groupBy('product_ref')
            ->toBase()
            ->get();

        $support = [];

        foreach ($rows as $row) {
            $support[Cast::str($row->product_ref)] = Cast::int($row->groups);
        }

        return $support;
    }

    /** @return Collection<int, \stdClass> */
    private static function pairs(string $tenantId, Carbon $since, Carbon $until): Collection
    {
        /** @var Collection<int, \stdClass> */
        return Signal::query()->from('recommendations_signals as a')
            ->join('recommendations_signals as b', function (JoinClause $join): void {
                $join->on('a.tenant_id', '=', 'b.tenant_id')
                    ->on('a.group_ref', '=', 'b.group_ref')
                    ->whereColumn('a.product_ref', '!=', 'b.product_ref');
            })
            ->where('a.tenant_id', $tenantId)
            ->where('a.kind', SignalKind::Purchase->value)
            ->where('b.kind', SignalKind::Purchase->value)
            ->where('a.group_ref', '!=', '')
            ->where('a.occurred_at', '>=', $since)
            ->where('a.occurred_at', '<=', $until)
            ->where('b.occurred_at', '>=', $since)
            ->where('b.occurred_at', '<=', $until)
            ->selectRaw('a.product_ref as from_ref, b.product_ref as to_ref, COUNT(DISTINCT a.group_ref) as groups, '.
                "COUNT(DISTINCT CASE WHEN a.subject_ref <> '' THEN a.subject_ref END) as subjects")
            ->groupBy('a.product_ref', 'b.product_ref')
            ->toBase()
            ->get();
    }
}
