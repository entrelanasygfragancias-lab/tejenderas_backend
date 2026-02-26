<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Attribute;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DefaultInventorySeeder extends Seeder
{
    public function run(): void
    {
        // Categorías y Subcategorías
        $telas = Category::create(['name' => 'Telas', 'slug' => 'telas']);
        $telas->subcategories()->createMany([
            ['name' => 'Algodón', 'slug' => 'algodon'],
            ['name' => 'Seda', 'slug' => 'seda'],
            ['name' => 'Lino', 'slug' => 'lino'],
        ]);

        $perfumeria = Category::create(['name' => 'Perfumería', 'slug' => 'perfumeria']);
        $perfumeria->subcategories()->createMany([
            ['name' => 'Fragancias Damas', 'slug' => 'fragancias-damas'],
            ['name' => 'Fragancias Caballeros', 'slug' => 'fragancias-caballeros'],
            ['name' => 'Esencias', 'slug' => 'esencias'],
        ]);

        // Atributos y Valores
        $color = Attribute::create(['name' => 'Color', 'slug' => 'color']);
        $color->values()->createMany([
            ['name' => 'Rojo', 'slug' => 'rojo', 'price_delta' => 0],
            ['name' => 'Azul', 'slug' => 'azul', 'price_delta' => 0],
            ['name' => 'Verde', 'slug' => 'verde', 'price_delta' => 0],
        ]);

        $talla = Attribute::create(['name' => 'Talla', 'slug' => 'talla']);
        $talla->values()->createMany([
            ['name' => 'S', 'slug' => 's', 'price_delta' => 0],
            ['name' => 'M', 'slug' => 'm', 'price_delta' => 5],
            ['name' => 'L', 'slug' => 'l', 'price_delta' => 10],
        ]);
    }
}
