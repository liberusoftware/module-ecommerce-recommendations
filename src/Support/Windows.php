<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Liberu\Ecommerce\Recommendations\Models\Signal;

final class Windows
{
    /** @return Builder<Signal> */
    public static function signals(string $tenantId, Carbon $since, Carbon $until): Builder
    {
        return Signal::query()
            ->where('tenant_id', $tenantId)
            ->where('occurred_at', '>=', $since)
            ->where('occurred_at', '<=', $until);
    }
}
