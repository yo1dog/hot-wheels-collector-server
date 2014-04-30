<?php
define('DB_HOST',     '');
define('DB_USER',     '');
define('DB_PASSWORD', '');
define('DB_NAME',     '');

define('HOTWHEELS2_IMAGE_PATH',                  '');
define('HOTWHEELS2_IMAGE_BASE_DIR',              'bases/');
define('HOTWHEELS2_IMAGE_DETAIL_DIR',            'details/');
define('HOTWHEELS2_IMAGE_ICON_DIR',              'icons/');
define('HOTWHEELS2_IMAGE_EXT',                   '.png');
define('HOTWHEELS2_IMAGE_CUSTOM_EXT',            '.jpeg');
define('HOTWHEELS2_IMAGE_BASE_SUFFIX',           '_base');
define('HOTWHEELS2_IMAGE_BASE_PROCESSED_SUFFIX', '_base_proc');
define('HOTWHEELS2_IMAGE_ICON_SUFFIX',           '_icon');
define('HOTWHEELS2_IMAGE_DETAIL_SUFFIX',         '_detail');
define('HOTWHEELS2_IMAGE_NAME_TRUNCATE_LENGTH',  32);

define('HOTWHEELS2_RESULTS_PER_PAGE',       100);
define('HOTWHEELS2_MAX_NUM_MOST_COLLECTED', 20);
define('HOTWHEELS2_MAX_NUM_REMOVALS',       20);

define('HOTWHEELS2_S3_IMAGES_BUCKET',               'HotWheels2_CarImages');
define('HOTWHEELS2_S3_IMAGES_DETAIL_KEY_BASE_PATH', 'details/');
define('HOTWHEELS2_S3_IMAGES_ICON_KEY_BASE_PATH',   'icons/');
define('HOTWHEELS2_S3_BASE_IMAGE_URL',              'http://s3.amazonaws.com/' . HOTWHEELS2_S3_IMAGES_BUCKET . '/');


define('MINE_LOG_FILE', '');

define('MINE_CAR_IMAGE_BASE_WIDTH',   960);
define('MINE_CAR_IMAGE_ICON_WIDTH',   300);
define('MINE_CAR_IMAGE_DETAIL_WIDTH', 640);

define('MINE_HWIP_LOCATION',    '');
define('MINE_CONVERT_LOCATION', 'convert');

define('MINE_HWIP_ALPHA_THRESHOLD', 30);
define('MINE_HWIP_PADDING',         30);
?>
