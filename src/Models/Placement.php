<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Liberu\Ecommerce\Recommendations\Enums\RefusalReason;

/**
 * What a surface asked for and what it was given. Recorded before it is
 * returned, so the explanation the shopper saw and the one an operator can
 * audit are the same row.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $slot
 * @property string $subject_ref
 * @property string $anchor_ref
 * @property int $requested
 * @property int $returned
 * @property int $candidates_examined
 * @property RefusalReason|null $refusal
 * @property bool $catalogue_checked
 * @property bool $cart_checked
 * @property int|null $seed
 * @property Carbon $served_at
 */
class Placement extends Model
{
    use RestatesTenant;

    protected $table = 'recommendations_placements';

    protected $fillable = [
        'tenant_id', 'slot', 'subject_ref', 'anchor_ref', 'requested', 'returned',
        'candidates_examined', 'refusal', 'catalogue_checked', 'cart_checked', 'seed', 'served_at',
    ];

    protected $casts = [
        'refusal' => RefusalReason::class,
        'requested' => 'integer',
        'returned' => 'integer',
        'candidates_examined' => 'integer',
        'catalogue_checked' => 'boolean',
        'cart_checked' => 'boolean',
        'seed' => 'integer',
        'served_at' => 'datetime',
    ];

    /** @return HasMany<PlacementEntry, $this> */
    public function entries(): HasMany
    {
        /** @var HasMany<PlacementEntry, $this> */
        return $this->scopedToTenant($this->hasMany(PlacementEntry::class, 'placement_id'));
    }
}
