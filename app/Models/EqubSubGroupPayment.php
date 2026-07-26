<?php

namespace App\Models;

use App\Enums\EqubPaymentMethod;
use App\Enums\EqubPaymentStatus;
use App\Services\CommissionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EqubSubGroupPayment extends Model
{
    protected $table = 'equb_sub_group_payments';

    protected $fillable = [
        'equb_sub_group_id',
        'member_id',
        'amount',
        'payment_date',
        'payment_method',
        'status',
        'reference',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'datetime',
            'payment_method' => EqubPaymentMethod::class,
            'status' => EqubPaymentStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EqubSubGroupPayment $payment): void {
            if ($payment->payment_method === EqubPaymentMethod::Chapa && empty($payment->reference)) {
                $payment->reference = 'SUB-EQUB-'.strtoupper(Str::random(12));
            }
        });
    }

    public function subGroup(): BelongsTo
    {
        return $this->belongsTo(EqubSubGroup::class, 'equb_sub_group_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function isPending(): bool
    {
        return $this->status === EqubPaymentStatus::Pending;
    }

    public function isPaid(): bool
    {
        return $this->status === EqubPaymentStatus::Paid;
    }
}