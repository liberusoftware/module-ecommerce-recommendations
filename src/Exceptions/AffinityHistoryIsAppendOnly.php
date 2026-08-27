<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Exceptions;

/** The unique index arbitrates a concurrent append; nothing about it stops an UPDATE. */
final class AffinityHistoryIsAppendOnly extends RecommendationsException
{
    public static function onUpdate(): self
    {
        return new self('An affinity event is written once and never changed.');
    }

    public static function onDelete(): self
    {
        return new self('An affinity event is never deleted.');
    }
}
