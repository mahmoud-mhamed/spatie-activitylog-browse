<?php

namespace Mhamed\SpatieActivitylogBrowse\Tests\Support;

use Illuminate\Database\Eloquent\Model;

class TestModel extends Model
{
    protected $table = 'test_models';

    protected $guarded = [];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'status' => StatusEnum::class,
            'priority' => PriorityEnum::class,
            'is_active' => 'boolean',
            'price' => 'decimal:2',
            'meta' => 'array',
        ];
    }
}
