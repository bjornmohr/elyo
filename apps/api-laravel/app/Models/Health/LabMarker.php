<?php

namespace App\Models\Health;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Catalog metadata for one lab marker.
 *
 * Orientation ranges are content candidates pending ELYO-94 review. This
 * health-domain model has no relation to identity-domain models.
 *
 * `marker_group` carries the contract field `group` (ELYO-102 §1.1); the column
 * is renamed because GROUP is a reserved word in Postgres.
 */
class LabMarker extends Model
{
    use HasFactory;

    protected $connection = 'health';

    protected $table = 'lab_markers';

    protected $primaryKey = 'marker_key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'marker_key',
        'name',
        'unit',
        'low',
        'high',
        'marker_group',
        'active',
    ];

    protected $casts = [
        'low' => 'decimal:4',
        'high' => 'decimal:4',
        'active' => 'boolean',
    ];
}
