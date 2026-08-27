<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Liberu\Ecommerce\Recommendations\Enums\AffinityState;
use Liberu\Ecommerce\Recommendations\Enums\Strategy;
use Liberu\Ecommerce\Recommendations\Exceptions\ScoreOutOfRange;

/**
 * A derived, directional, scored claim between two catalogue references, owned
 * by a strategy. Never a foreign key: the catalogue is referenced and may not
 * even share a database.
 *
 * @property int $id
 * @property string $tenant_id
 * @property Strategy $strategy
 * @property string $from_ref
 * @property string $to_ref
 * @property string $score
 * @property int $evidence_count
 * @property int $subject_count
 * @property AffinityState $state
 * @property int|null $run_id
 * @property Carbon $asserted_at
 * @property Carbon|null $superseded_at
 */
class Affinity extends Model
{
    use RestatesTenant;

    /** Popularity is about the store, so its claims sit under no anchor. */
    public const ANCHORLESS = '';

    protected $table = 'recommendations_affinities';

    protected $fillable = [
        'tenant_id', 'strategy', 'from_ref', 'to_ref', 'score', 'evidence_count',
        'subject_count', 'state', 'run_id', 'asserted_at', 'superseded_at',
    ];

    protected $casts = [
        'strategy' => Strategy::class,
        'state' => AffinityState::class,
        'evidence_count' => 'integer',
        'subject_count' => 'integer',
        'asserted_at' => 'datetime',
        'superseded_at' => 'datetime',
    ];

    /** The schema says a ratio; this is what makes the code say it too. */
    public function setScoreAttribute(float|int|string $score): void
    {
        $value = (float) $score;

        if (! is_finite($value) || $value < 0.0 || $value > 1.0) {
            throw ScoreOutOfRange::for($value);
        }

        $this->attributes['score'] = number_format($value, 6, '.', '');
    }

    public function ratio(): float
    {
        return (float) $this->score;
    }

    public function isActive(): bool
    {
        return $this->state === AffinityState::Active;
    }

    /** @return HasMany<AffinityEvent, $this> */
    public function events(): HasMany
    {
        /** @var HasMany<AffinityEvent, $this> */
        return $this->scopedToTenant($this->hasMany(AffinityEvent::class, 'affinity_id'));
    }
}
