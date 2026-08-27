<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Data;

use Liberu\Ecommerce\Recommendations\Enums\RefusalReason;

final readonly class PruneReport
{
    public function __construct(
        public int $deleted = 0,
        public int $unbounded = 0,
        public ?RefusalReason $refusal = null,
    ) {}

    public function wasRefused(): bool
    {
        return $this->refusal !== null;
    }
}
