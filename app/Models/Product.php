<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'model',
        'sku',
        'upc',
        'ean',
        'jan',
        'isbn',
        'mpn',
        'location',
        'quantity',
        'stock_status_id',
        'image',
        'manufacturer_id',
        'shipping',
        'price',
        'points',
        'tax_class_id',
        'date_available',
        'weight',
        'weight_class_id',
        'length',
        'width',
        'height',
        'length_class_id',
        'subtract',
        'minimum',
        'sort_order',
        'status',
        'viewed',
    ];

    protected $casts = [
        'shipping' => 'boolean',
        'subtract' => 'boolean',
        'status' => 'boolean',
        'date_available' => 'date',
        'price' => 'decimal:4',
        'weight' => 'decimal:8',
        'length' => 'decimal:8',
        'width' => 'decimal:8',
        'height' => 'decimal:8',
    ];

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function descriptions(): HasMany
    {
        return $this->hasMany(ProductDescription::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_product');
    }
}
