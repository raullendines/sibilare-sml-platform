<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

trait UsesUuidPrimaryKey
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;
}
