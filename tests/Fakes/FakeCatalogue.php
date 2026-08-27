<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Tests\Fakes;

use Liberu\Ecommerce\Recommendations\Contracts\CatalogueReader;
use Liberu\Ecommerce\Recommendations\Data\CatalogueItem;

final class FakeCatalogue implements CatalogueReader
{
    public int $asked = 0;

    /** @param  array<string, CatalogueItem>  $items */
    public function __construct(public array $items = []) {}

    /**
     * @param  list<string>  $productRefs
     * @return array<string, CatalogueItem>
     */
    public function describe(string $tenantId, array $productRefs): array
    {
        $this->asked++;
        $known = [];

        foreach ($productRefs as $ref) {
            if (isset($this->items[$ref])) {
                $known[$ref] = $this->items[$ref];
            }
        }

        return $known;
    }
}
