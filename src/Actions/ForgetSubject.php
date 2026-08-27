<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Actions;

use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\Recommendations\Data\ForgetReport;
use Liberu\Ecommerce\Recommendations\Events\SubjectForgotten;
use Liberu\Ecommerce\Recommendations\Models\Affinity;
use Liberu\Ecommerce\Recommendations\Models\Placement;
use Liberu\Ecommerce\Recommendations\Models\Signal;
use Liberu\Ecommerce\Recommendations\Support\Cast;

/**
 * A person is not a tenant's property, so this walks every tenant — the same
 * set the export walks, which is what makes the two agree about "everything".
 *
 * The asymmetry is deliberate and argued in the ADR: the person's signals and
 * placements go, and the affinities their behaviour contributed to stay,
 * because an affinity is a statement about a pair of products. The anonymity
 * floor on generation is what makes that statement anonymous rather than a
 * profile of one shopper wearing an aggregate's clothes.
 */
final class ForgetSubject
{
    public function __invoke(string $subjectRef): ForgetReport
    {
        if ($subjectRef === '') {
            return new ForgetReport($subjectRef, 0, 0, 0);
        }

        $placements = Placement::query()->where('subject_ref', $subjectRef)->get();

        foreach ($placements as $placement) {
            $placement->entries()->delete();
        }

        $placementCount = $placements->count();
        Placement::query()->where('subject_ref', $subjectRef)->delete();
        $signalCount = Cast::int(Signal::query()->where('subject_ref', $subjectRef)->delete());
        $retained = Affinity::query()->count();

        $report = new ForgetReport($subjectRef, $signalCount, $placementCount, $retained);
        Event::dispatch(new SubjectForgotten($subjectRef, $signalCount, $placementCount, $retained));

        return $report;
    }
}
