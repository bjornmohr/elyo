<?php

namespace App\Models\Health;

use Illuminate\Database\Eloquent\Model;

class HealthSubject extends Model
{
    public const STATUS_ACTIVE = 'ACTIVE';

    protected $connection = 'health';

    protected $table = 'health_subjects';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['*'];
}
