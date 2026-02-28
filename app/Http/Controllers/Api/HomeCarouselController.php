<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HomeCarouselSetting;
use Illuminate\Http\Request;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class HomeCarouselController extends Controller
{
    public function show()
    {
        $setting = HomeCarouselSetting::query()->latest()->first();

        return response()->json([
            'banners' => $setting?->banners ?? [],
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'banners' => 'required|array|min:1',
            'banners.*.title' => 'required|string|max:255',
            'banners.*.subtitle' => 'required|string|max:500',
            'banners.*.ctaText' => 'required|string|max:255',
            'banners.*.ctaLink' => 'required|string|max:255',
            'banners.*.promoText' => 'required|string|max:255',
            'banners.*.promoLink' => 'required|string|max:255',
            'banners.*.imageUrl' => 'nullable|string',
            'banners.*.bgClass' => 'required|string|max:50',
            'banners.*.textClass' => 'required|string|max:50',
        ]);

        $setting = HomeCarouselSetting::query()->latest()->first();

        if ($setting) {
            $setting->update(['banners' => $validated['banners']]);
        } else {
            $setting = HomeCarouselSetting::create(['banners' => $validated['banners']]);
        }

        return response()->json([
            'message' => 'Carrusel actualizado correctamente.',
            'banners' => $setting->banners,
        ]);
    }
}
