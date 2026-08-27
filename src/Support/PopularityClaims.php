<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Support;

use Illuminate\Support\Carbon;
use Liberu\Ecommerce\Recommendations\Data\Claim;
use Liberu\Ecommerce\Recommendations\Models\Affinity;

/**
 * The share of the window's shoppers who touched a product. A ratio against a
 * figure the window measured, so it means the same thing in a store with a
 * hundred shoppers and one with a million.
 */
final class PopularityClaims
{
    /** @return list<Claim> */
    public static function for(string $tenantId, Carbon $since, Carbon $until): array
    {
        $shoppers = self::distinctSubjects($tenantId, $since, $until);

        if ($shoppers === 0) {
            return [];
        }

        $rows = Windows::signals($tenantId, $since, $until)
            ->selectRaw('product_ref, COUNT(*) as signals, '.
                "COUNT(DISTINCT CASE WHEN subject_ref <> '' THEN subject_ref END) as subjects")
            ->groupBy('product_ref')
            ->toBase()
            ->get();

        $claims = [];

        foreach ($rows as $row) {
            $subjects = Cast::int($row->subjects);

            $claims[] = new Claim(
                Affinity::ANCHORLESS,
                Cast::str($row->product_ref),
                min(1.0, $subjects / $shoppers),
                Cast::int($row->signals),
                $subjects,
            );
        }

        return $claims;
    }

    private static function distinctSubjects(string $tenantId, Carbon $since, Carbon $until): int
    {
        return Cast::int(Windows::signals($tenantId, $since, $until)
            ->where('subject_ref', '!=', '')
            ->distinct()
            ->count('subject_ref'));
    }
}
