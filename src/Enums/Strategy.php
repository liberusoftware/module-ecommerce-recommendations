<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Enums;

/**
 * Named and closed. The host's free-string `type` column made adding a strategy
 * a row somebody typed, with no code that knew what to do with it.
 */
enum Strategy: string
{
    case Collaborative = 'collaborative';
    case ContentSimilarity = 'content_similarity';
    case Popularity = 'popularity';
    case Manual = 'manual';

    /** A curated claim outranks a computed one of any score. */
    public function isManual(): bool
    {
        return $this === self::Manual;
    }

    /** Popularity is about the store, so its claims carry no anchor. */
    public function isAnchored(): bool
    {
        return $this !== self::Popularity;
    }

    /**
     * Whether a claim from this strategy is a statement about people.
     * A category overlap is a statement about the catalogue, so no floor
     * applies to it; a co-purchase and a view count are about shoppers.
     */
    public function describesSubjects(): bool
    {
        return $this === self::Collaborative || $this === self::Popularity;
    }

    /** @return list<self> */
    public static function generated(): array
    {
        return [self::Collaborative, self::ContentSimilarity, self::Popularity];
    }
}
