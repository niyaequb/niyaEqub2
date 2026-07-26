<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EqubSubGroupDraw extends Model
{
    protected $fillable = [
        'draw_type',
        'target_members',
        'draw_date',
        'executed_by_admin_id',
    ];

    protected $casts = [
        'draw_date' => 'datetime',
    ];

    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by_admin_id');
    }

    // Relationship to winning sub groups
    public function winners(): BelongsToMany
    {
        return $this->belongsToMany(
            EqubSubGroup::class, 
            'equb_sub_group_draw_winners', 
            'draw_id', 
            'sub_group_id'
        );
    }
}