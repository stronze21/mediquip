<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'purchase_order_item_id',
        'batch_number',
        'lot_number',
        'manufactured_date',
        'expiry_date',
        'quantity_received',
        'quantity_on_hand',
        'unit_cost',
        'received_at',
        'supplier_name',
        'notes',
    ];

    protected $casts = [
        'manufactured_date' => 'date',
        'expiry_date' => 'date',
        'quantity_received' => 'integer',
        'quantity_on_hand' => 'integer',
        'unit_cost' => 'decimal:2',
        'received_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function getIsExpiredAttribute()
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function getIsExpiringSoonAttribute()
    {
        return $this->expiry_date
            && !$this->is_expired
            && $this->expiry_date->lte(now()->addDays(90));
    }
}
