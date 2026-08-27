<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Models;

use Illuminate\Database\Eloquent\Model;
use Liberu\Ecommerce\Recommendations\Enums\ExclusionReason;
use Liberu\Ecommerce\Recommendations\Enums\Strategy;
use Liberu\Ecommerce\Recommendations\Exceptions\ScoreOutOfRange;

/**
 * One candidate the placement considered: a position and no reason, or a
 * reason and no position. An excluded candidate is kept so "you asked for ten
 * and got four" has an answer.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $placement_id
 * @property string $product_ref
 * @property Strategy $strategy
 * @property string $raw_score
 * @property string $normalised_score
 * @property int $evidence_count
 * @property int|null $position
 * @property ExclusionReason|null $excluded_for
 */
class PlacementEntry extends Model
{
    protected $table = 'recommendations_placement_entries';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'placement_id', 'product_ref', 'strategy', 'raw_score',
        'normalised_score', 'evidence_count', 'position', 'excluded_for',
    ];

    protected $casts = [
        'strategy' => Strategy::class,
        'excluded_for' => ExclusionReason::class,
        'evidence_count' => 'integer',
        'position' => 'integer',
    ];

    public function setRawScoreAttribute(float|int|string $score): void
    {
        $this->attributes['raw_score'] = self::ratio($score);
    }

    public function setNormalisedScoreAttribute(float|int|string $score): void
    {
        $this->attributes['normalised_score'] = self::ratio($score);
    }

    private static function ratio(float|int|string $score): string
    {
        $value = (float) $score;

        if (! is_finite($value) || $value < 0.0 || $value > 1.0) {
            throw ScoreOutOfRange::for($value);
        }

        return number_format($value, 6, '.', '');
    }
}
