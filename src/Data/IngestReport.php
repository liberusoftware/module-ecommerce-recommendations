<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Data;

use Liberu\Ecommerce\Recommendations\Enums\RefusalReason;

final readonly class IngestReport
{
    public function __construct(
        public string $tenantId,
        public int $offered = 0,
        public int $recorded = 0,
        public int $alreadyRecorded = 0,
        public int $refusedRefs = 0,
        public ?RefusalReason $refusal = null,
    ) {}

    public function wasRefused(): bool
    {
        return $this->refusal !== null;
    }
}
