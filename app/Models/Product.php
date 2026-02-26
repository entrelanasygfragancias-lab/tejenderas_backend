<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Attribute;
use App\Models\AttributeValue;

class Product extends Model
{
    protected $fillable = [
        'barcode',
        'name',
        'category',
        'brand',
        'subcategory',
        'category_id',
        'subcategory_id',
        'is_promo',
        'is_combo',
        'description',
        'base_price',
        'markup',
        'markup_type',
        'price',
        'stock',
        'stock_in_total',
        'stock_out_total',
        'image',
        'images',
        'variants',
    ];

    protected $casts = [
        'variants' => 'array',
        'images' => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory()
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function attributes()
    {
        return $this->belongsToMany(Attribute::class, 'product_attributes');
    }

    public function attributeValues()
    {
        return $this->belongsToMany(AttributeValue::class, 'product_attribute_values')
                    ->withPivot(['price_delta', 'image', 'base_price', 'markup', 'markup_type', 'stock'])
                    ->withTimestamps();
    }
}
