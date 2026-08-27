<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Contracts;

use Illuminate\Support\Carbon;
use Liberu\Ecommerce\Recommendations\Data\Interaction;

/**
 * Where a host offers the interactions it already observes.
 *
 * Analytics owns the observation; this module owns the inference. Nothing here
 * tracks a page view, and nothing bound by default means the module ingests
 * nothing rather than inventing a shopper.
 */
interface SignalSource
{
    /** @return iterable<int, Interaction> */
    public function interactions(string $tenantId, Carbon $since, Carbon $until): iterable;
}
