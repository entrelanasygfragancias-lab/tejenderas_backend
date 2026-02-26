<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AttributeController extends Controller
{
    public function index()
    {
        return response()->json(Attribute::with('values')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:attributes',
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $attribute = Attribute::create($validated);

        return response()->json($attribute, 201);
    }

    public function update(Request $request, Attribute $attribute)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:attributes,name,' . $attribute->id,
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $attribute->update($validated);

        return response()->json($attribute);
    }

    public function destroy(Attribute $attribute)
    {
        $attribute->delete();
        return response()->json(['message' => 'Atributo eliminado']);
    }

    public function storeValue(Request $request, Attribute $attribute)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price_delta' => 'nullable|numeric',
        ]);

        $value = $attribute->values()->create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'price_delta' => $validated['price_delta'] ?? 0,
        ]);

        return response()->json($value, 201);
    }

    public function updateValue(Request $request, Attribute $attribute, \App\Models\AttributeValue $value)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price_delta' => 'nullable|numeric',
        ]);

        $value->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'price_delta' => $validated['price_delta'] ?? 0,
        ]);

        return response()->json($value);
    }

    public function destroyValue(Attribute $attribute, \App\Models\AttributeValue $value)
    {
        $value->delete();
        return response()->json(['message' => 'Valor de atributo eliminado']);
    }
}
