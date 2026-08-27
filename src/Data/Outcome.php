<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Data;

use Liberu\Ecommerce\Recommendations\Enums\Recording;
use Liberu\Ecommerce\Recommendations\Enums\RefusalReason;

/** What every mutation returns. No write here is a silent no-op. */
final readonly class Outcome
{
    private function __construct(
        public Recording $recording,
        public ?RefusalReason $reason = null,
        public ?int $id = null,
    ) {}

    public static function recorded(?int $id = null): self
    {
        return new self(Recording::Recorded, null, $id);
    }

    public static function alreadyRecorded(?int $id = null): self
    {
        return new self(Recording::AlreadyRecorded, null, $id);
    }

    public static function refused(RefusalReason $reason): self
    {
        return new self(Recording::Refused, $reason);
    }

    public function happened(): bool
    {
        return $this->recording === Recording::Recorded;
    }

    public function wasRefused(): bool
    {
        return $this->recording === Recording::Refused;
    }
}
