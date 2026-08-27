<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Data;

use Liberu\Ecommerce\Recommendations\Enums\RefusalReason;
use Liberu\Ecommerce\Recommendations\Enums\RunState;
use Liberu\Ecommerce\Recommendations\Enums\Strategy;

final readonly class RunReport
{
    public function __construct(
        public int $runId,
        public string $tenantId,
        public Strategy $strategy,
        public RunState $state,
        public int $windowDays,
        public int $candidatesIn,
        public int $asserted,
        public int $superseded,
        public int $withheldBelowFloor,
        public int $kAnonymityFloor,
        public ?RefusalReason $failure = null,
    ) {}

    public function succeeded(): bool
    {
        return $this->state === RunState::Succeeded;
    }
}
