<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Data;

final readonly class CatalogueItem
{
    /**
     * @param  list<string>  $categoryRefs
     * @param  list<string>  $tagRefs
     */
    public function __construct(
        public string $productRef,
        public bool $inStock = true,
        public bool $suppressed = false,
        public array $categoryRefs = [],
        public array $tagRefs = [],
    ) {}

    /** @return list<string> */
    public function traits(): array
    {
        return array_values(array_unique(array_merge(
            array_map(static fn (string $ref): string => 'category:'.$ref, $this->categoryRefs),
            array_map(static fn (string $ref): string => 'tag:'.$ref, $this->tagRefs),
        )));
    }
}
