<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\Attribute;

echo "--- ATTRIBUTES & VALUES ---\n";
foreach (Attribute::with('values')->get() as $a) {
    echo "Attribute: {$a->name} (ID: {$a->id})\n";
    foreach ($a->values as $v) {
        echo "  - Value: {$v->name} (ID: {$v->id})\n";
    }
}

echo "\n--- LATEST PRODUCTS SUMMARY ---\n";
foreach (Product::with('attributeValues')->latest()->take(5)->get() as $p) {
    echo "Product ID: {$p->id}, Name: {$p->name}, Price: {$p->price}, Stock: {$p->stock}\n";
    foreach ($p->attributeValues as $v) {
        echo "  - Variant: {$v->name} (ID: {$v->id}), Stock: {$v->pivot->stock}, Price Delta: {$v->pivot->price_delta}\n";
    }
}
