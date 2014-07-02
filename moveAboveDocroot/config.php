<?php
// database
define('DB_HOST',     '');
define('DB_USER',     '');
define('DB_PASSWORD', '');
define('DB_NAME',     '');


// result limiting
define('RESULTS_PER_PAGE',                    100);
define('MAX_NUM_COLLECTION_REMOVALS_RESULTS', 20);
define('MAX_NUM_MOST_COLLECTED_RESULTS',      20);



// car images
define('CAR_IMAGE_BASE_PATH', 'change_me_in_config_php'); // base path to store the car images in. Include ending slash. Recommend using an absolute path. eg: /path/www/img/

define('S3_CAR_IMAGE_BUCKET',        ''); // S3 bucket to store the car images in
define('S3_CAR_IMAGE_BUCKET_CUSTOM', ''); // S3 bucket to store the custom car images in
define('S3_URL',                     'http://s3.amazonaws.com');

define('CAR_IMAGE_WIDTH_BASE',   960); // width of the car image to download from Hot Wheels' server
define('CAR_IMAGE_WIDTH_ICON',   300); // final width of the car icon image
define('CAR_IMAGE_WIDTH_DETAIL', 640); // final width of the car detail image


// external commands
define('EXTERNAL_COMMAND_HWIP',   'change_me_in_config_php'); // command to execute the hwip binary. Recommend using an absolute path. eg hwip OR /path/hwip
define('EXTERNAL_COMMAND_RESIZE', 'convert');                 // command to execure the binary to resize images. Default is to use ImageMagick's convert.
define('EXTERNAL_COMMAND_AWS',    'aws');                     // command to execure the aws binary

define('AWS_CONFIG_FILE', 'change_me_in_config_php'); // path to aws config. Recommend using an absolute path. eg /path/aws_config

define('HWIP_ALPHA_THRESHOLD', 30);
define('HWIP_PADDING',         30);


// mine
define('MINE_LOG_FILE', 'change_me_in_config_php'); // path to the log file for mine to use. Recommend using an absolute path. eg /path/mine.log
?>
