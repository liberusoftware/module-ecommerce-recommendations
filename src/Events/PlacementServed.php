<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Events;

use Liberu\Ecommerce\Recommendations\Enums\RefusalReason;

final readonly class PlacementServed
{
    public function __construct(
        public string $tenantId,
        public int $placementId,
        public string $slot,
        public int $requested,
        public int $returned,
        public ?RefusalReason $refusal,
    ) {}
}
