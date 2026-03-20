<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerTransaction extends Model
{
    protected $fillable = [
        'reseller_id',
        'admin_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'reference',
        'subscriber_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reseller_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    // أنواع المعاملات
    public static function getTypes(): array
    {
        return [
            'deposit' => ['label' => 'إيداع', 'color' => 'green', 'icon' => 'fa-plus-circle'],
            'withdraw' => ['label' => 'سحب', 'color' => 'red', 'icon' => 'fa-minus-circle'],
            'purchase' => ['label' => 'شراء', 'color' => 'blue', 'icon' => 'fa-shopping-cart'],
            'refund' => ['label' => 'استرجاع', 'color' => 'yellow', 'icon' => 'fa-undo'],
            'commission' => ['label' => 'عمولة', 'color' => 'purple', 'icon' => 'fa-percentage'],
        ];
    }

    public function getTypeLabelAttribute(): string
    {
        return self::getTypes()[$this->type]['label'] ?? $this->type;
    }

    public function getTypeColorAttribute(): string
    {
        return self::getTypes()[$this->type]['color'] ?? 'gray';
    }

    public function getTypeIconAttribute(): string
    {
        return self::getTypes()[$this->type]['icon'] ?? 'fa-circle';
    }

    // إنشاء معاملة جديدة
    public static function createTransaction(
        User $reseller,
        string $type,
        float $amount,
        string $description,
        ?User $admin = null,
        ?string $reference = null,
        ?Subscriber $subscriber = null
    ): self {
        $balanceBefore = $reseller->balance;
        
        // تحديث الرصيد
        if (in_array($type, ['deposit', 'refund', 'commission'])) {
            $reseller->increment('balance', $amount);
        } else {
            $reseller->decrement('balance', $amount);
        }
        
        return self::create([
            'reseller_id' => $reseller->id,
            'admin_id' => $admin?->id,
            'type' => $type,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $reseller->fresh()->balance,
            'description' => $description,
            'reference' => $reference,
            'subscriber_id' => $subscriber?->id,
        ]);
    }
}
