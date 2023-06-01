<?php

namespace Validations;

class Validations {

    /**
     * Validate if the given Image URL is valid.
     * If not, return error 404 and abort.
     * @param $imageUrl
     * @return Boolean
     */
    public static function validateGivenImage($imageUrl) {
        if ($imageUrl == null || $imageUrl == '') {
            error_log('Image not informed');
            header('HTTP/1.0 404 Not Found');
            exit;
        }
        return true;
    }


    /**
     * Validate if the given dimensions are valid.
     * If not, return error 404 and abort.
     * @param $width
     * @param $height
     * @return Boolean
     */
    public static function validateGivenDimensions($width, $height) {
        if (!is_numeric($width) || !is_numeric($height)) {
            error_log('Dimension not informed');
            header('HTTP/1.0 404 Not Found');
            exit;
        }
        return true;
    }

}