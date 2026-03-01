<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\UploadedFile;

try {
    // Create a dummy image
    $imagePath = __DIR__.'/storage/app/public/test_image.jpg';
    $img = imagecreatetruecolor(100, 100);
    imagejpeg($img, $imagePath);
    imagedestroy($img);
    
    $file = new UploadedFile($imagePath, 'test_image.jpg', 'image/jpeg', null, true);
    
    echo "Starting upload...\n";
    $result = Cloudinary::upload($file->getRealPath());
    echo "Upload Success! Path: " . $result->getSecurePath() . "\n";
} catch (\Throwable $e) {
    echo "Error caught:\n";
    echo $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
