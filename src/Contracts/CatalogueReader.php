<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Contracts;

use Liberu\Ecommerce\Recommendations\Data\CatalogueItem;

/**
 * Where a product reference becomes something the module can reason about.
 * The catalogue is referenced and never joined, and may not share a database.
 */
interface CatalogueReader
{
    /**
     * A ref the catalogue does not know must be absent from the result rather
     * than answered with a blank item; the serve path reports it.
     *
     * @param  list<string>  $productRefs
     * @return array<string, CatalogueItem>
     */
    public function describe(string $tenantId, array $productRefs): array;
}
