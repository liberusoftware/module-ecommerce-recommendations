<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Actions;

use Illuminate\Support\Carbon;
use Liberu\Ecommerce\Recommendations\Data\IngestReport;
use Liberu\Ecommerce\Recommendations\Enums\RefusalReason;
use Liberu\Ecommerce\Recommendations\Support\Seams;

/**
 * The pull half: a host that already observes interactions offers them here.
 * Unbound, nothing is ingested and the refusal says which seam was missing —
 * this module does not re-implement page-view tracking.
 */
final class IngestSignals
{
    public function __invoke(string $tenantId, Carbon $since, Carbon $until): IngestReport
    {
        $source = Seams::signalSource();

        if ($source === null) {
            return new IngestReport($tenantId, refusal: RefusalReason::NoSignalSourceBound);
        }

        $record = new RecordSignal();
        $offered = $recorded = $already = $refused = 0;

        foreach ($source->interactions($tenantId, $since, $until) as $interaction) {
            $offered++;
            $outcome = $record($tenantId, $interaction);

            match (true) {
                $outcome->happened() => $recorded++,
                $outcome->wasRefused() => $refused++,
                default => $already++,
            };
        }

        return new IngestReport($tenantId, $offered, $recorded, $already, $refused);
    }
}
