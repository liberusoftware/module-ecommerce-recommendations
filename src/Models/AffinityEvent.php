<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Liberu\Ecommerce\Recommendations\Enums\AffinityState;
use Liberu\Ecommerce\Recommendations\Exceptions\AffinityHistoryIsAppendOnly;

/**
 * The audit row a state change writes. Append-only needs both halves: the
 * unique index arbitrates a concurrent append, and these guards stop a rewrite.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $affinity_id
 * @property int $sequence
 * @property AffinityState|null $from_state
 * @property AffinityState $to_state
 * @property int|null $run_id
 * @property Carbon $occurred_at
 */
class AffinityEvent extends Model
{
    protected $table = 'recommendations_affinity_events';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'affinity_id', 'sequence', 'from_state', 'to_state', 'run_id', 'occurred_at',
    ];

    protected $casts = [
        'from_state' => AffinityState::class,
        'to_state' => AffinityState::class,
        'sequence' => 'integer',
        'occurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw AffinityHistoryIsAppendOnly::onUpdate();
        });

        static::deleting(function (): never {
            throw AffinityHistoryIsAppendOnly::onDelete();
        });
    }
}
