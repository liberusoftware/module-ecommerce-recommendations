<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Actions;

use Illuminate\Database\QueryException;
use Liberu\Ecommerce\Recommendations\Data\Interaction;
use Liberu\Ecommerce\Recommendations\Data\Outcome;
use Liberu\Ecommerce\Recommendations\Enums\RefusalReason;
use Liberu\Ecommerce\Recommendations\Models\Signal;
use Liberu\Ecommerce\Recommendations\Support\Settings;

/**
 * The push half of the seam: whatever the caller says happened, recorded once.
 * No session is invented — a signal with no subject is a popularity input.
 *
 * The natural key is scoped to the subject as well as the tenant, so two people
 * quoting one reference get two rows rather than one person's row twice.
 */
final class RecordSignal
{
    public function __invoke(string $tenantId, Interaction $interaction): Outcome
    {
        if ($interaction->productRef === '' || $interaction->sourceRef === '') {
            return Outcome::refused(RefusalReason::ProductReferenceRequired);
        }

        $days = Settings::signalRetentionDays();
        $retainUntil = null;

        // Only a subject-keyed signal has a person to forget, so only one
        // carries a window.
        if ($interaction->hasSubject() && $days !== null) {
            $retainUntil = $interaction->occurredAt->copy()->addDays($days);
        }

        try {
            $signal = Signal::query()->create([
                'tenant_id' => $tenantId,
                'subject_ref' => $interaction->subjectRef,
                'product_ref' => $interaction->productRef,
                'group_ref' => $interaction->groupRef,
                'kind' => $interaction->kind,
                'source_ref' => $interaction->sourceRef,
                'occurred_at' => $interaction->occurredAt,
                'retain_until' => $retainUntil,
            ]);
        } catch (QueryException $exception) {
            $existing = Signal::query()
                ->where('tenant_id', $tenantId)
                ->where('subject_ref', $interaction->subjectRef)
                ->where('source_ref', $interaction->sourceRef)
                ->first();

            if (! $existing instanceof Signal) {
                throw $exception;
            }

            return Outcome::alreadyRecorded($existing->id);
        }

        return Outcome::recorded($signal->id);
    }
}
