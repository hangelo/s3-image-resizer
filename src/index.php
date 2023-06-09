<?php

require __DIR__.'/../vendor/autoload.php';

use ResizeImage\ResizeImage;
use Validations\Validations;

// Collect parameters
$width = isset($_GET['w']) ? trim($_GET['w']) : 300;
$height = isset($_GET['h']) ? trim($_GET['h']) : 300;
$imageUrl = isset($_GET['img']) ? trim($_GET['img']) : null;
$crop = isset($_GET['c']) ? trim($_GET['c']) : 'f';
$ignoreCache = isset($_GET['f']) ? trim($_GET['f']) : false;
$version = isset($_GET['v']) ? trim($_GET['v']) : '1';

//ini_set( 'display_errors', 1 );

//$imageUrl = 'galerias/emp-164/leiloes/lei-2258/lotes/lot-622127/qMxxL6NM.jpg';

// Validate given parameters
Validations::validateGivenImage($imageUrl);
Validations::validateGivenDimensions($width, $height);

// Process
$resizeImage = new ResizeImage($imageUrl, $width, $height, 'crop', $version);
if ($resizeImage->isFileExistLocal() || $resizeImage->isFileExistInBucket()) {
    $resizeImage->downloadImageResized();
}
else {
    $resizeImage->downloadImageOriginal();
    $resizeImage->resizeImage();
    $resizeImage->uploadImage();
}
$outputImage = $resizeImage->outputImage();
