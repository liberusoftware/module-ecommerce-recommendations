<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Actions;

use Illuminate\Support\Carbon;
use Liberu\Ecommerce\Recommendations\Data\PruneReport;
use Liberu\Ecommerce\Recommendations\Enums\RefusalReason;
use Liberu\Ecommerce\Recommendations\Models\Signal;
use Liberu\Ecommerce\Recommendations\Support\Cast;
use Liberu\Ecommerce\Recommendations\Support\Settings;

/**
 * The retention window, enforced. Unconfigured is not a window of zero: the
 * prune refuses rather than deciding on the host's behalf, and reports how many
 * subject-keyed signals are standing with no window at all.
 */
final class PruneExpiredSignals
{
    public function __invoke(?Carbon $asOf = null): PruneReport
    {
        $unbounded = Signal::query()->where('subject_ref', '!=', '')->whereNull('retain_until')->count();

        if (Settings::signalRetentionDays() === null) {
            return new PruneReport(0, $unbounded, RefusalReason::RetentionWindowUnknown);
        }

        $deleted = Cast::int(Signal::query()
            ->whereNotNull('retain_until')
            ->where('retain_until', '<=', $asOf ?? Carbon::now())
            ->delete());

        return new PruneReport($deleted, $unbounded);
    }
}
