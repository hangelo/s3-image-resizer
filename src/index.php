<?php

require __DIR__.'/../vendor/autoload.php';


use \Gumlet\ImageResize;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Symfony\Component\Yaml\Yaml;

$width = isset($_GET['w']) ? trim($_GET['w']) : 90;
$height = isset($_GET['h']) ? trim($_GET['h']) : 90;
$imageUrl = isset($_GET['img']) ? trim($_GET['img']) : null;

// Validate if an image is given
if ($imageUrl == null || $imageUrl != '') {
    header('HTTP/1.0 404 Not Found');
    exit;
}

class ResizeImage {

    private $s3Client;

    private $AWS_ACCESS_KEY;
    private $AWS_SECRET_KEY;
    private $AWS_REGION;
    private $AWS_VERSION;

    private $CACHE_CONTROL = 31536000;

    private $debugMode;

    private $BUCKET_NAME = 'imgs.iarremate';
    private $BASE_URL = 'https://s3-sa-east-1.amazonaws.com/imgs.iarremate/';
    private $LOCAL_CACHE_DIR = __DIR__.'/../cache/';
    private $RESIZE_QUALITY = 100;

    private $dimensionWidth;
    private $dimensionHeight;

    private $imagePath;
    private $imageExtension;

    private $imageFileNameOriginal;
    private $imageFullNameOriginal;

    private $imageFileNameResized;
    private $imageFullNameResized;

    private $fileResized;

    /**
     * Downloadded file data
     */
    private $downloadedFileData;

    public static function boolToText($booleanValue) {
        return $booleanValue ? 'true' : 'false';
    }

    private function outputDump($message) {
        if ($this->debugMode) {
            dump($message);
        }
    }

    /**
     * @param String $imagePath
     * @param Integer $width
     * @param Integer $height
     * @param String $mode
     */
    function __construct($imageUrl, $width, $height, $mode) {
        // Load the AWS credentials and other settings
        $ymlFile = Yaml::parseFile('environment.yml');
        $this->AWS_ACCESS_KEY = $ymlFile['AWS_ACCESS_KEY_ID'];
        $this->AWS_SECRET_KEY = $ymlFile['AWS_SECRET_ACCESS_KEY'];
        $this->AWS_REGION = $ymlFile['AWS_REGION'];
        $this->AWS_VERSION = $ymlFile['AWS_VERSION'];
        $this->debugMode = $ymlFile['debug_mode'];

        // Store the given dimentions
        $this->dimensionWidth = $width;
        $this->dimensionHeight = $height;

        // Handle the file names
        $this->imagePath = dirname($imageUrl);
        $this->imageExtension = pathinfo($imageUrl, PATHINFO_EXTENSION);
        $this->imageFileNameOriginal = pathinfo($imageUrl, PATHINFO_FILENAME).'.'.$this->imageExtension;
        $this->imageFullNameOriginal = $this->imagePath.'/'.$this->imageFileNameOriginal;
        $this->imageFileNameResized = pathinfo($imageUrl, PATHINFO_FILENAME).'-w'.$width.'-h'.$height.'-m'.$mode.'.'.$this->imageExtension;
        $this->imageFullNameResized = $this->imagePath.'/'.$this->imageFileNameResized;
    }

    /**
     * Download the image into a local file.
     * Download from the remote if not already stored locally
     */
    private function downloadImage($imageFileName) {
        if (file_exists($this->LOCAL_CACHE_DIR.$imageFileName)) {
            $this->outputDump('Loading local image');
            $this->downloadedFileData = file_get_contents($this->LOCAL_CACHE_DIR.$imageFileName);
        }
        else {
            $this->outputDump('Downloading image from S3 Bucket');
            $imageObject = $this->getS3Instance()->getObject([
                'Bucket' => $this->BUCKET_NAME,
                'Key' => $this->imagePath.'/'.$imageFileName,
            ]);
            $this->downloadedFileData = $imageObject['Body']->getContents();
            file_put_contents($this->LOCAL_CACHE_DIR.$imageFileName, $this->downloadedFileData);
        }
    }

    public function downloadImageOriginal() {
        return $this->downloadImage($this->imageFileNameOriginal);
    }

    public function downloadImageResized() {
        return $this->downloadImage($this->imageFileNameResized);
    }

    public function isFileExistLocal() {
        $fileExist = file_exists($this->LOCAL_CACHE_DIR.$this->imageFileNameResized);
        $this->outputDump('File exist local: '.self::boolToText($fileExist));
        return $fileExist;
    }

    public function isFileExistInBucket() {
        $fileExist = $this->getS3Instance()->doesObjectExist($this->BUCKET_NAME, $this->imageFullNameResized);
        $this->outputDump('File exist in bucket: '.self::boolToText($fileExist));
        return $fileExist;
    }

    /**
     * Resize the downloaded image
     */
    public function resizeImage() {
        $this->outputDump('Resizing image');
        $image = new ImageResize('data:image/jpeg;base64,'.base64_encode($this->downloadedFileData));
        $image->quality_jpg = $this->RESIZE_QUALITY;
        // Crop the image by resizing til the smaller side and centered crop the larger side
        $image->crop($this->dimensionWidth, $this->dimensionHeight, true, ImageResize::CROPCENTER);

        // Save the resized image as a file
        $image->save($this->LOCAL_CACHE_DIR.$this->imageFileNameResized);
    }

    private function getS3Instance() {
        if ($this->s3Client == null) {
            $this->s3Client = new S3Client([
                'version' => 'latest',
                'region' => $this->AWS_REGION,
                'credentials' => [
                    'key' => $this->AWS_ACCESS_KEY,
                    'secret' => $this->AWS_SECRET_KEY,
                ],
            ]);
        }
        return $this->s3Client;
    }

    public function uploadImage() {
        $this->outputDump('Pushing resized image to S3 Bucket');
        try {
            // Upload the image file to S3 bucket
            $result = $this->getS3Instance()->putObject([
                'Bucket' => $this->BUCKET_NAME,
                'Key' => $this->imageFullNameResized,
                'SourceFile' => $this->LOCAL_CACHE_DIR.$this->imageFileNameResized,
                'ACL' => 'public-read', // Optional: Set appropriate ACL permissions
                'CacheControl' => 'max-age='.$this->CACHE_CONTROL, // Set cache control for 1 year (in seconds)
                'Metadata' => array(
                    'dimension-width' => $this->dimensionWidth,
                    'dimension-height' => $this->dimensionHeight,
                )
            ]);

            // Return the public URL of the uploaded image
            $this->outputDump($result['ObjectURL']);
            return $result['ObjectURL'];
        } catch (S3Exception $e) {
            throw new Exception('Error uploading the image: ' . $e->getMessage());
        }
    }

    public function outputImage() {
        $localFile = $this->LOCAL_CACHE_DIR.$this->imageFileNameResized;

        // Clear any previous buffer
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Add the content-type in the header
        $extension = pathinfo($localFile, PATHINFO_EXTENSION);
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                header('Content-Type: image/jpeg');
                break;
            case 'png':
                header('Content-Type: image/png');
                break;
            case 'gif':
                header('Content-Type: image/gif');
                break;
            default:
                header('Content-Type: application/octet-stream');
                break;
        }

        // Cache control
        header('Cache-Control: max-age='.$this->CACHE_CONTROL);
        $etag = md5_file($localFile);
        header('ETag: '.$etag);

        // Check if the client already has a cached version
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag) {
            // Client has a valid cached version, send 304 Not Modified status
            header('HTTP/1.1 304 Not Modified');
            exit;
        }

        // Output just the image content
        readfile($localFile);
    }

}

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
