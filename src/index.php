<?php

require __DIR__.'/../vendor/autoload.php';

use ResizeImage\ResizeImage;
use Validations\Validations;

// Collect parameters
$width = isset($_GET['w']) ? trim($_GET['w']) : 90;
$height = isset($_GET['h']) ? trim($_GET['h']) : 90;
$imageUrl = isset($_GET['img']) ? trim($_GET['img']) : null;

// Validate given parameters
Validations::validateGivenImage($imageUrl);
Validations::validateGivenDimensions($width, $height);

// Process
$resizeImage = new ResizeImage($imageUrl, $width, $height, 'crop');
if ($resizeImage->isFileExistLocal() || $resizeImage->isFileExistInBucket()) {
    $resizeImage->downloadImageResized();
}
else {
    $resizeImage->downloadImageOriginal();
    $resizeImage->resizeImage();
    $resizeImage->uploadImage();
}
$resizeImage->outputImage();
