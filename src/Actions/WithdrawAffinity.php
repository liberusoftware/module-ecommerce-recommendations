<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Actions;

use Liberu\Ecommerce\Recommendations\Data\Outcome;
use Liberu\Ecommerce\Recommendations\Enums\AffinityState;
use Liberu\Ecommerce\Recommendations\Enums\RefusalReason;
use Liberu\Ecommerce\Recommendations\Models\Affinity;
use Liberu\Ecommerce\Recommendations\Policies\CustodyPolicy;
use Liberu\Ecommerce\Recommendations\Support\Transitions;

/** Retracting a claim by hand. A run retracts its own; nothing else retracts a manual one. */
final class WithdrawAffinity
{
    public function __invoke(string $tenantId, Affinity $affinity): Outcome
    {
        if (! CustodyPolicy::ownsAffinity($affinity, $tenantId)) {
            return Outcome::refused(RefusalReason::NotThisTenants);
        }

        return Transitions::to($affinity, AffinityState::Superseded, null)
            ? Outcome::recorded($affinity->id)
            : Outcome::alreadyRecorded($affinity->id);
    }
}
