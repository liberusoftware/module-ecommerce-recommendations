<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Support;

use Illuminate\Support\Facades\Config;

/** The configured numbers, read where they are used and never cached at boot. */
final class Settings
{
    public static function kAnonymityFloor(): int
    {
        return max(1, Cast::int(Config::get('recommendations.k_anonymity.minimum_subjects', 5)));
    }

    /** Null is a host that never said, which is not a window of zero. */
    public static function signalRetentionDays(): ?int
    {
        $days = Config::get('recommendations.retention.signal_days');

        return is_numeric($days) ? max(1, Cast::int($days)) : null;
    }

    public static function candidateOverfetch(): int
    {
        return max(1, Cast::int(Config::get('recommendations.serve.candidate_overfetch', 3)));
    }
}
