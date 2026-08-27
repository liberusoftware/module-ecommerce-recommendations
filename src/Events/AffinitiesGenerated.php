<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Events;

use Liberu\Ecommerce\Recommendations\Enums\Strategy;

/** References and scalars only, and always the tenant. */
final readonly class AffinitiesGenerated
{
    public function __construct(
        public string $tenantId,
        public Strategy $strategy,
        public int $runId,
        public int $asserted,
        public int $superseded,
        public int $withheldBelowFloor,
    ) {}
}
