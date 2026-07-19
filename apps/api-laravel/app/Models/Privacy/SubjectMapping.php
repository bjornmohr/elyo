<?php

namespace App\Models\Privacy;

use Illuminate\Database\Eloquent\Model;

class SubjectMapping extends Model
{
    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_REVOKED = 'REVOKED';

    protected $connection = 'mapping';

    protected $table = 'subject_mappings';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'revoked_at' => 'datetime',
        ];
    }
}
