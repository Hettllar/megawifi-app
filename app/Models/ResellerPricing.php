<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerPricing extends Model
{
    protected $table = 'reseller_pricing';

    protected $fillable = [
        'router_id',
        'service_type',
        'pricing_type',
        'price_per_unit',
        'currency',
        'is_active',
        'description',
    ];

    protected $casts = [
        'price_per_unit' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    // أنواع الخدمات
    public static function getServiceTypes(): array
    {
        return [
            'hotspot' => 'Hotspot',
            'ppp' => 'PPP/PPPoE',
            'usermanager' => 'UserManager',
        ];
    }

    // أنواع التسعير
    public static function getPricingTypes(): array
    {
        return [
            'per_gb' => 'لكل جيجابايت',
            'per_day' => 'لكل يوم',
            'per_week' => 'لكل أسبوع',
            'per_month' => 'لكل شهر',
            'fixed' => 'سعر ثابت',
        ];
    }

    // العملات
    public static function getCurrencies(): array
    {
        return [
            'SYP' => 'ليرة سورية',
            'IQD' => 'دينار عراقي',
            'USD' => 'دولار أمريكي',
            'TRY' => 'ليرة تركية',
        ];
    }

    // حساب السعر بناءً على البيانات
    public function calculatePrice(float $dataGb = 0, int $days = 0): float
    {
        return match($this->pricing_type) {
            'per_gb' => $this->price_per_unit * $dataGb,
            'per_day' => $this->price_per_unit * $days,
            'per_week' => $this->price_per_unit * ceil($days / 7),
            'per_month' => $this->price_per_unit * ceil($days / 30),
            'fixed' => $this->price_per_unit,
            default => 0,
        };
    }

    public function getFormattedPriceAttribute(): string
    {
        $currencySymbols = [
            'SYP' => 'ل.س',
            'IQD' => 'د.ع',
            'USD' => '$',
            'TRY' => '₺',
        ];
        
        $symbol = $currencySymbols[$this->currency] ?? $this->currency;
        $typeLabel = self::getPricingTypes()[$this->pricing_type] ?? '';
        
        return number_format($this->price_per_unit, 0) . " {$symbol} / {$typeLabel}";
    }
}
