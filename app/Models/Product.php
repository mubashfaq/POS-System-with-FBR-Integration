<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use HasFactory;
    protected $fillable = [
        'business_id',
        'image',
        'name',
        'slug',
        'sku',
        'description',
        'category_id',
        'brand_id',
        'unit_id',
        'default_packing_id',
        'price',
        'discount',
        'discount_type',
        'purchase_price',
        'quantity',
        'has_variations',
        'expire_date',
        'status',
        'hsCode',
        'hs_description',
        'rate_of_tax',
        'salesTaxWithheldAtSource',
        'sale_type',
        'sro_item',
        'uom',
        'PCTCode',
        'pos_sale_tax',
        'sro_schedule',
        'fixed_notified_value_or_retail_price'



    ];
    protected $appends = ['discounted_price'];

    protected $casts = [
        'has_variations' => 'boolean',
        'price' => 'decimal:4',
        'purchase_price' => 'decimal:4',
    ];

    public function setNameAttribute($value)
    {
        $this->attributes['name'] = $value;
        if (!$this->exists || empty($this->attributes['slug'])) {
            $this->attributes['slug'] = $this->generateSlug($value);
        }
    }

    /**
     * Generate a unique slug for the category.
     *
     * @param string $name
     * @return string
     */
    protected function generateSlug($name)
    {
        $slug = Str::slug($name);
        $count = static::where('slug', 'like', "$slug%")->count();

        return $count ? "{$slug}-{$count}" : $slug;
    }
    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class, 'product_id');
    }

    /**
     * Accessories defined for this product (via product_accessories pivot).
     * Each accessory includes a 'quantity' field (how many units needed per 1 parent unit).
     */
    public function accessories(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_accessories', 'product_id', 'accessory_product_id')
            ->withPivot('quantity')->withTimestamps();
    }

    /**
     * Parent products that use this product as an accessory.
     */
    public function parentProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_accessories', 'accessory_product_id', 'product_id')
            ->withPivot('quantity')->withTimestamps();
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function defaultPacking()
    {
        return $this->belongsTo(Packing::class, 'default_packing_id');
    }

    public function unitPrices()
    {
        return $this->hasMany(ProductUnitPrice::class, 'product_id');
    }

    public function variations()
    {
        return $this->hasMany(ProductVariation::class);
    }

    /**
     * All variations including soft-deleted ones (for history/admin views).
     */
    public function allVariations()
    {
        return $this->hasMany(ProductVariation::class)->withTrashed();
    }

    public function getQuantityAttribute($value): float
    {
        if (array_key_exists('stock_sum', $this->attributes)) {
            return round((float) $this->attributes['stock_sum'], 4);
        }

        return round((float) $this->purchaseItems()->sum('remaining_qty'), 4);
    }
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
    public function scopeStocked($query)
    {
        return $query->whereRaw(
            '(select coalesce(sum(pi.remaining_qty), 0) from purchase_items pi where pi.product_id = products.id) > 0'
        );
    }

    /**
     * Get available purchase batches (with remaining stock), oldest first.
     */
    public function availableBatches()
    {
        return $this->purchaseItems()->where('remaining_qty', '>', 0)->orderBy('created_at', 'asc');
    }
    public function getDiscountedPriceAttribute()
    {
        if ($this->discount_type == 'fixed') {
            $discountedPrice = (float) $this->price - (float) $this->discount;
        } elseif ($this->discount_type == 'percentage') {
            $discountedPrice = (float) $this->price - ((float) $this->price * (float) $this->discount / 100);
        } else {
            $discountedPrice = (float) $this->price;
        }
        return round((float) $discountedPrice, 4);
    }

}
