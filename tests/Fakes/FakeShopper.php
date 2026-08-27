<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Tests\Fakes;

use Liberu\Ecommerce\Recommendations\Contracts\ShopperContext;

final class FakeShopper implements ShopperContext
{
    public int $asked = 0;

    /** @param  list<string>  $cart */
    public function __construct(public array $cart = []) {}

    /** @return list<string> */
    public function cartRefs(string $tenantId, string $subjectRef): array
    {
        $this->asked++;

        return $this->cart;
    }
}
