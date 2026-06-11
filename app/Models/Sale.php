<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'customer_id',
        'promotion_id',
        'promotion_code',
        'invoice_type',
        'tax_type',
        'tax_rate',
        'warehouse_id',
        'user_id',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'change_amount',
        'payment_method',
        'payment_terms',
        'due_date',
        'payment_status',
        'status',
        'notes',
        'completed_at'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function warrantyClaims()
    {
        return $this->hasMany(WarrantyClaim::class);
    }

    public function getTaxableGrossAmountAttribute(): float
    {
        if (in_array($this->tax_type, ['ewt_sales_1', 'ewt_service_2'], true)) {
            return (float) $this->total_amount + (float) $this->tax_amount;
        }

        return max(0, (float) $this->subtotal - (float) $this->discount_amount);
    }

    public function getSubtotalAmountAttribute(): float
    {
        if (in_array($this->tax_type, ['vat_12', 'ewt_sales_1', 'ewt_service_2'], true)) {
            return $this->taxable_gross_amount / 1.12;
        }

        return $this->taxable_gross_amount;
    }

    public function getTaxLabelAttribute(): string
    {
        return match ($this->tax_type) {
            'vat_12' => 'VAT (12% inclusive)',
            'ewt_sales_1' => 'EWT (1% on sales, net of VAT)',
            'ewt_service_2' => 'EWT (2% on services, net of VAT)',
            'mixed' => 'Mixed Tax',
            default => 'Non-VAT',
        };
    }

    public function getOutstandingBalanceAttribute(): float
    {
        return max(0, (float) $this->total_amount - (float) $this->paid_amount);
    }

    public function getIsPaidAttribute(): bool
    {
        return ($this->payment_status ?? 'paid') === 'paid' || $this->outstanding_balance <= 0;
    }

    public function getDaysDelayedAttribute(): int
    {
        if ($this->is_paid || !$this->due_date) {
            return 0;
        }

        return (int) max(0, $this->due_date->copy()->startOfDay()->diffInDays(now()->startOfDay(), false));
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        if ($this->is_paid) {
            return 'Paid';
        }

        $status = ucfirst($this->payment_status ?? 'unpaid');

        if ($this->days_delayed > 0) {
            return "{$status} ({$this->days_delayed} days delayed)";
        }

        return $status;
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return $this->payment_method === 'terms'
            ? 'Payment Terms'
            : ucfirst(str_replace('_', ' ', $this->payment_method));
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->invoice_number)) {
                $model->invoice_number = 'INV-' . date('Ymd') . '-' . str_pad(Sale::whereDate('created_at', today())->count() + 1, 4, '0', STR_PAD_LEFT);
            }
        });
    }
}
