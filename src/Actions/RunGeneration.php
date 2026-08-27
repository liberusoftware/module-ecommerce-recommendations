<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\Recommendations\Data\Claim;
use Liberu\Ecommerce\Recommendations\Data\RunReport;
use Liberu\Ecommerce\Recommendations\Enums\AffinityState;
use Liberu\Ecommerce\Recommendations\Enums\RefusalReason;
use Liberu\Ecommerce\Recommendations\Enums\RunState;
use Liberu\Ecommerce\Recommendations\Enums\Strategy;
use Liberu\Ecommerce\Recommendations\Events\AffinitiesGenerated;
use Liberu\Ecommerce\Recommendations\Models\Affinity;
use Liberu\Ecommerce\Recommendations\Models\GenerationRun;
use Liberu\Ecommerce\Recommendations\Support\CollaborativeClaims;
use Liberu\Ecommerce\Recommendations\Support\ContentSimilarityClaims;
use Liberu\Ecommerce\Recommendations\Support\PopularityClaims;
use Liberu\Ecommerce\Recommendations\Support\Seams;
use Liberu\Ecommerce\Recommendations\Support\Settings;
use Liberu\Ecommerce\Recommendations\Support\Transitions;

/**
 * Generation is a run, not a side effect. What the newest successful run for a
 * strategy did not reassert is superseded, so a pair that stopped qualifying
 * stops being recommended instead of keeping its last score forever.
 */
final class RunGeneration
{
    public function __invoke(string $tenantId, Strategy $strategy, int $windowDays = 30, ?Carbon $asOf = null): RunReport
    {
        $floor = Settings::kAnonymityFloor();
        $until = $asOf ?? Carbon::now();
        $since = $until->copy()->subDays($windowDays);

        $run = GenerationRun::query()->create([
            'tenant_id' => $tenantId,
            'strategy' => $strategy,
            'window_days' => $windowDays,
            'state' => RunState::Running,
            'k_anonymity_floor' => $floor,
            'started_at' => Carbon::now(),
        ]);

        if ($strategy->isManual()) {
            return $this->fail($run, RefusalReason::ManualIsNotGenerated, $floor);
        }

        $claims = match ($strategy) {
            Strategy::Collaborative => CollaborativeClaims::for($tenantId, $since, $until),
            Strategy::Popularity => PopularityClaims::for($tenantId, $since, $until),
            default => null,
        };

        if ($claims === null) {
            $catalogue = Seams::catalogue();

            if ($catalogue === null) {
                return $this->fail($run, RefusalReason::NoCatalogueReaderBound, $floor);
            }

            $claims = ContentSimilarityClaims::for($tenantId, $since, $until, $catalogue);
        }

        [$asserted, $withheld] = $this->assert($tenantId, $strategy, $claims, $run->id, $floor);
        $superseded = $this->supersede($tenantId, $strategy, $asserted, $run->id);

        $run->forceFill([
            'state' => RunState::Succeeded,
            'candidates_in' => count($claims),
            'asserted' => count($asserted),
            'superseded' => $superseded,
            'withheld_below_floor' => $withheld,
            'finished_at' => Carbon::now(),
        ])->save();

        Event::dispatch(new AffinitiesGenerated($tenantId, $strategy, $run->id, count($asserted), $superseded, $withheld));

        return new RunReport(
            $run->id, $tenantId, $strategy, RunState::Succeeded, $windowDays,
            count($claims), count($asserted), $superseded, $withheld, $floor,
        );
    }

    /**
     * @param  list<Claim>  $claims
     * @return array{0: list<int>, 1: int}
     */
    private function assert(string $tenantId, Strategy $strategy, array $claims, int $runId, int $floor): array
    {
        $asserted = [];
        $withheld = 0;

        foreach ($claims as $claim) {
            // A claim about people that fewer than the floor of them stand
            // behind is withheld: an aggregate that can single somebody out is
            // not the anonymous statistic erasure assumes it is.
            if ($strategy->describesSubjects() && $claim->subjectCount < $floor) {
                $withheld++;

                continue;
            }

            $affinity = Affinity::query()->firstOrNew([
                'tenant_id' => $tenantId,
                'strategy' => $strategy,
                'from_ref' => $claim->fromRef,
                'to_ref' => $claim->toRef,
            ]);

            $existed = $affinity->exists;

            $affinity->fill([
                'score' => $claim->score,
                'evidence_count' => $claim->evidenceCount,
                'subject_count' => $claim->subjectCount,
                'run_id' => $runId,
                'asserted_at' => Carbon::now(),
            ]);

            if (! $existed) {
                $affinity->state = AffinityState::Active;
            }

            $affinity->save();

            if ($existed) {
                Transitions::to($affinity, AffinityState::Active, $runId);
            } else {
                Transitions::opened($affinity, $runId);
            }

            $asserted[] = $affinity->id;
        }

        return [$asserted, $withheld];
    }

    /** @param  list<int>  $asserted */
    private function supersede(string $tenantId, Strategy $strategy, array $asserted, int $runId): int
    {
        $stale = Affinity::query()
            ->where('tenant_id', $tenantId)
            ->where('strategy', $strategy->value)
            ->where('state', AffinityState::Active->value)
            ->whereNotIn('id', $asserted === [] ? [0] : $asserted)
            ->get();

        foreach ($stale as $affinity) {
            Transitions::to($affinity, AffinityState::Superseded, $runId);
        }

        return $stale->count();
    }

    private function fail(GenerationRun $run, RefusalReason $reason, int $floor): RunReport
    {
        $run->forceFill([
            'state' => RunState::Failed,
            'failure_reason' => $reason->value,
            'finished_at' => Carbon::now(),
        ])->save();

        return new RunReport(
            $run->id, $run->tenant_id, $run->strategy, RunState::Failed, $run->window_days,
            0, 0, 0, 0, $floor, $reason,
        );
    }
}
