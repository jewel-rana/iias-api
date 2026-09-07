<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommitteeRole extends Model
{
    protected $fillable = [
        'name',
        'code',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'bool',
            'sort_order' => 'integer',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(CommitteeMember::class);
    }
}
