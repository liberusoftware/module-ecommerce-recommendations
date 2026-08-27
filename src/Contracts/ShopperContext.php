<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Contracts;

/** Where a shopper's live cart is read. Past purchases come from signals. */
interface ShopperContext
{
    /** @return list<string> */
    public function cartRefs(string $tenantId, string $subjectRef): array;
}
