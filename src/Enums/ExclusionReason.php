<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Enums;

/** One list, evaluated once, and every removal counted on the placement. */
enum ExclusionReason: string
{
    case IsAnchor = 'is_anchor';
    case AlreadyPurchased = 'already_purchased';
    case AlreadyInCart = 'already_in_cart';
    case OutOfStock = 'out_of_stock';
    case Suppressed = 'suppressed';
    case UnresolvableRef = 'unresolvable_ref';
}
