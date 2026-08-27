<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Liberu\Ecommerce\Recommendations\Enums\SignalKind;

/**
 * A derived, purpose-limited input to a ranking — not an analytics event. The
 * two describe the same click and are not the same record.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $subject_ref
 * @property string $product_ref
 * @property string $group_ref
 * @property SignalKind $kind
 * @property string $source_ref
 * @property Carbon $occurred_at
 * @property Carbon|null $retain_until
 */
class Signal extends Model
{
    protected $table = 'recommendations_signals';

    protected $fillable = [
        'tenant_id', 'subject_ref', 'product_ref', 'group_ref', 'kind', 'source_ref', 'occurred_at', 'retain_until',
    ];

    protected $casts = [
        'kind' => SignalKind::class,
        'occurred_at' => 'datetime',
        'retain_until' => 'datetime',
    ];

    /** Empty is a real answer: nobody said who, which popularity can still use. */
    public function hasSubject(): bool
    {
        return $this->subject_ref !== '';
    }
}
