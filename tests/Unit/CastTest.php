<?php

declare(strict_types=1);

use Liberu\Ecommerce\Recommendations\Support\Cast;

it('narrows an untyped aggregate value or answers nothing', function (): void {
    expect(Cast::int('12'))->toBe(12)
        ->and(Cast::int(7))->toBe(7)
        ->and(Cast::int(null))->toBe(0)
        ->and(Cast::int(['a']))->toBe(0)
        ->and(Cast::str('sku-1'))->toBe('sku-1')
        ->and(Cast::str(4))->toBe('4')
        ->and(Cast::str(null))->toBe('')
        ->and(Cast::str(['a']))->toBe('');
});
