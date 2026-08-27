<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Liberu\Ecommerce\Recommendations\Enums\RunState;
use Liberu\Ecommerce\Recommendations\Enums\Strategy;

/**
 * Generation is a run, not a side effect. Retraction is the run's job: what the
 * newest successful run did not reassert is superseded rather than left
 * standing at whatever score it last had.
 *
 * @property int $id
 * @property string $tenant_id
 * @property Strategy $strategy
 * @property int $window_days
 * @property RunState $state
 * @property int $candidates_in
 * @property int $asserted
 * @property int $superseded
 * @property int $withheld_below_floor
 * @property int $k_anonymity_floor
 * @property string|null $failure_reason
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 */
class GenerationRun extends Model
{
    use RestatesTenant;

    protected $table = 'recommendations_generation_runs';

    protected $fillable = [
        'tenant_id', 'strategy', 'window_days', 'state', 'candidates_in', 'asserted',
        'superseded', 'withheld_below_floor', 'k_anonymity_floor', 'failure_reason',
        'started_at', 'finished_at',
    ];

    protected $casts = [
        'strategy' => Strategy::class,
        'state' => RunState::class,
        'window_days' => 'integer',
        'candidates_in' => 'integer',
        'asserted' => 'integer',
        'superseded' => 'integer',
        'withheld_below_floor' => 'integer',
        'k_anonymity_floor' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /** @return HasMany<Affinity, $this> */
    public function affinities(): HasMany
    {
        /** @var HasMany<Affinity, $this> */
        return $this->scopedToTenant($this->hasMany(Affinity::class, 'run_id'));
    }
}
