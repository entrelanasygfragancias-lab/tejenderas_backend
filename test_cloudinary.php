<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

try {
    $imagePath = __DIR__.'/storage/app/public/test_image.jpg';
    $img = imagecreatetruecolor(100, 100);
    imagejpeg($img, $imagePath);
    imagedestroy($img);
    
    $file = new UploadedFile($imagePath, 'test_image.jpg', 'image/jpeg', null, true);
    
    // Method 1: native cloudinary
    $cloudinary = app(\Cloudinary\Cloudinary::class);
    $result = $cloudinary->uploadApi()->upload($file->getRealPath());
    echo "Upload Success (native)! Path: " . $result['secure_url'] . "\n";
    
    // Method 2: Storage facade
    $path = Storage::disk('cloudinary')->put('products', $file);
    echo "Upload Success (Storage)! URL: " . Storage::disk('cloudinary')->url($path) . "\n";
} catch (\Throwable $e) {
    echo "Error caught:\n";
    echo $e->getMessage() . "\n";
}
