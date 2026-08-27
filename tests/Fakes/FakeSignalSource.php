<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Tests\Fakes;

use Illuminate\Support\Carbon;
use Liberu\Ecommerce\Recommendations\Contracts\SignalSource;
use Liberu\Ecommerce\Recommendations\Data\Interaction;

final class FakeSignalSource implements SignalSource
{
    public int $asked = 0;

    public ?Carbon $sawSince = null;

    /** @param  list<Interaction>  $offers */
    public function __construct(public array $offers = []) {}

    /** @return iterable<int, Interaction> */
    public function interactions(string $tenantId, Carbon $since, Carbon $until): iterable
    {
        $this->asked++;
        $this->sawSince = $since;

        return $this->offers;
    }
}
