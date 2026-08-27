<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Enums;

/** Closed, so a new kind is a code change with a test rather than a row. */
enum SignalKind: string
{
    case View = 'view';
    case AddToCart = 'add_to_cart';
    case Purchase = 'purchase';
    case Wishlist = 'wishlist';
    case Rate = 'rate';
}
