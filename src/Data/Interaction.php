<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Data;

use Illuminate\Support\Carbon;
use Liberu\Ecommerce\Recommendations\Enums\SignalKind;

/**
 * One thing a shopper did, as the caller states it. The subject may be absent,
 * which is a popularity input and not an error; the module never invents one.
 */
final readonly class Interaction
{
    public function __construct(
        public string $productRef,
        public SignalKind $kind,
        public string $sourceRef,
        public Carbon $occurredAt,
        public string $subjectRef = '',
        public string $groupRef = '',
    ) {}

    public function hasSubject(): bool
    {
        return $this->subjectRef !== '';
    }
}
