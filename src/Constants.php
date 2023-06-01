<?php

namespace Constants;

class Constants {

    public static $CACHE_CONTROL = 31536000;

    public static $BUCKET_NAME = 'imgs.iarremate';
    public static $BASE_URL = 'https://s3-sa-east-1.amazonaws.com/imgs.iarremate/';
    public static $LOCAL_CACHE_DIR = __DIR__.'/../cache/';
    public static $RESIZE_QUALITY = 100;

}