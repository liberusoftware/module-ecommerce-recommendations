<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Support;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Liberu\Ecommerce\Recommendations\Contracts\CatalogueReader;
use Liberu\Ecommerce\Recommendations\Contracts\ShopperContext;
use Liberu\Ecommerce\Recommendations\Contracts\SignalSource;

/**
 * Resolved at the moment of use, so a host rebinding takes effect on the next
 * call. Nothing is bound by default: null means nobody answered, which is not
 * an answer of nothing.
 *
 * `App`/`Config` facades rather than `app()`/`config()`, which live in
 * `laravel/framework` and not in `illuminate/support`.
 */
final class Seams
{
    public static function signalSource(): ?SignalSource
    {
        return self::resolve('recommendations.seams.signal_source', SignalSource::class);
    }

    public static function catalogue(): ?CatalogueReader
    {
        return self::resolve('recommendations.seams.catalogue', CatalogueReader::class);
    }

    public static function shopper(): ?ShopperContext
    {
        return self::resolve('recommendations.seams.shopper', ShopperContext::class);
    }

    /**
     * @template TContract of object
     *
     * @param  class-string<TContract>  $contract
     * @return TContract|null
     */
    private static function resolve(string $key, string $contract): ?object
    {
        $configured = Config::get($key);

        if ($configured instanceof $contract) {
            return $configured;
        }

        if (is_string($configured) && $configured !== '') {
            $made = App::make($configured);

            return $made instanceof $contract ? $made : null;
        }

        if (! App::bound($contract)) {
            return null;
        }

        $bound = App::make($contract);

        return $bound instanceof $contract ? $bound : null;
    }
}
