<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EqubSubGroup extends Model
{
    protected $table = 'equb_sub_groups';

    protected $fillable = [
        'equb_group_id',
        'name',
        'inviter_member_id',
        'has_won',
        'win_date',
    ];

    protected $casts = [
        'has_won' => 'boolean',
        'win_date' => 'datetime',
    ];

    public function equbGroup(): BelongsTo
    {
        return $this->belongsTo(EqubGroup::class, 'equb_group_id');
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'inviter_member_id');
    }

    public function members()
    {
        return $this->belongsToMany(Member::class, 'equb_sub_group_member', 'equb_sub_group_id', 'member_id')
                    ->withTimestamps();
    }

    // UPDATED: Pointing directly to EqubSubGroupPayment
    public function payments(): HasMany
    {
        return $this->hasMany(EqubSubGroupPayment::class, 'equb_sub_group_id');
    }
}