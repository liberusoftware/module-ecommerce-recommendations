<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Policies;

use Liberu\Ecommerce\Recommendations\Models\Affinity;
use Liberu\Ecommerce\Recommendations\Models\Placement;

/**
 * Standing, in one place, and every check takes the tenant. A check that
 * forgets to say which merchant it is asking about passes at both.
 */
final class CustodyPolicy
{
    public static function ownsAffinity(Affinity $affinity, string $tenantId): bool
    {
        return $affinity->tenant_id === $tenantId;
    }

    public static function ownsPlacement(Placement $placement, string $tenantId): bool
    {
        return $placement->tenant_id === $tenantId;
    }

    /** A shopper reads the placement served to them, not the one served to a merchant's admin. */
    public static function subjectMayRead(Placement $placement, string $tenantId, string $subjectRef): bool
    {
        return self::ownsPlacement($placement, $tenantId)
            && $subjectRef !== ''
            && $placement->subject_ref === $subjectRef;
    }
}
