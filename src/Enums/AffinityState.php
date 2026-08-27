<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Enums;

enum AffinityState: string
{
    case Active = 'active';
    case Superseded = 'superseded';

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowed(), true);
    }

    /** @return list<self> */
    public function allowed(): array
    {
        return match ($this) {
            self::Active => [self::Superseded],
            self::Superseded => [self::Active],
        };
    }
}
