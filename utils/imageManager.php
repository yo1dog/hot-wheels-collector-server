<?php
define('CAR_IMAGE_SUB_PATH',              'cars/');
define('CAR_IMAGE_SUB_PATH_CUSTOM',       'carsCustom/');
define('CAR_IMAGE_SUB_PATH_TEMP',         'temp/');

define('CAR_IMAGE_SUB_PATH_BASE',         'bases/');
define('CAR_IMAGE_SUB_PATH_ICON',         'icons/');
define('CAR_IMAGE_SUB_PATH_DETAIL',       'details/');

define('CAR_IMAGE_SUFFIX_BASE',           '_base');
define('CAR_IMAGE_SUFFIX_PROCESSED_BASE', '_proc_base');
define('CAR_IMAGE_SUFFIX_ICON',           '_icon');
define('CAR_IMAGE_SUFFIX_DETAIL',         '_detail');

define('CAR_IMAGE_EXT',                   '.png');
define('CAR_IMAGE_EXT_CUSTOM',            '.jpeg');
define('CAR_IMAGE_EXT_TEMP',              '.temp');


define('CAR_IMAGE_TYPE_BASE'          , 0);
define('CAR_IMAGE_TYPE_PROCESSED_BASE', 1);
define('CAR_IMAGE_TYPE_ICON'          , 2);
define('CAR_IMAGE_TYPE_DETAIL'        , 3);


define('S3_CAR_IMAGE_KEY_BASE_PATH_ICON',   'icons/');
define('S3_CAR_IMAGE_KEY_BASE_PATH_DETAIL', 'details/');


require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/externalProcessor.php';

/**
 * Image Manager
 * 
 * Provides functions for generating images, copying images to S3, and retrieving image filenames.
 */
class ImageManager
{
	/**
	 * Process Car Base Image
	 *
	 * Uses HWIP to trim and process the base image.
	 * 
	 * @param string $baseImageFilename Filename of the base image to process.
	 * @param string $newImageFilename  Filename to output the processed base image to.
	 */
	public static function processCarBaseImage($baseImageFilename, $newImageFilename)
	{
		return ExternalProcessor::executeExternalProcess(EXTERNAL_COMMAND_HWIP . ' ' .  escapeshellarg($baseImageFilename) . ' ' .  escapeshellarg($newImageFilename) . ' ' . HWIP_ALPHA_THRESHOLD . ' ' . HWIP_PADDING);
	}
	
	/**
	 * Generate Car Image
	 *
	 * Resizes and generates an image from the processed base image.
	 * 
	 * @param string $fromFilename     Filename of the image to generate the new image from.
	 * @param string $newImageFilename Filename to output the generated image to.
	 */
	public static function generateCarImage($fromFilename, $newImageFilename, $imageWidth)
	{
		return ExternalProcessor::executeExternalProcess(EXTERNAL_COMMAND_RESIZE . ' ' .  escapeshellarg($fromFilename) . ' -resize ' .  escapeshellarg($imageWidth) . ' ' .  escapeshellarg($newImageFilename));
	}
	
	/**
	 * Copy Image To S3
	 *
	 * Copies an image to S3.
	 * 
	 * @param string $imageFilename Filename of the image to copy to S3.
	 * @param string $s3Bucket      S3 bucket to copy the image into.
	 * @param string $s3KeyPath     Path of the S3 key to copy the image into. Should include final slash.
	 */
	public static function copyImageToS3($imageFilename, $s3Bucket, $s3KeyBasePath)
	{
		if (!is_string($imageFilename) || strlen($imageFilename) === 0)
			throw new Exception('Non-string or empty image filename given to S3 copy');
		
		putenv('AWS_CONFIG_FILE=' . AWS_CONFIG_FILE);
		return ExternalProcessor::executeExternalProcess(EXTERNAL_COMMAND_AWS . ' s3 cp ' . escapeshellarg($imageFilename) . ' ' . escapeshellarg('s3://' . $s3Bucket . '/' . $s3KeyBasePath));
	}
	
	/**
	 * Sync Images With S3
	 *
	 * Synchronizes images with S3.
	 * 
	 * @param string $imagesPath Path to the images to sync.
	 * @param string $s3Bucket   S3 bucket to sync the images with.
	 * @param string $s3KeyPath  Path of the S3 key to sync the images with. Should include final slash.
	 */
	public static function syncImagesWithS3($imagesPath, $s3Bucket, $s3KeyBasePath)
	{
		if (!is_string($imagesPath) || strlen($imagesPath) === 0)
			throw new Exception('Non-string or empty images path given to S3 sync');
		
		putenv('AWS_CONFIG_FILE=' . AWS_CONFIG_FILE);
		return ExternalProcessor::executeExternalProcess(EXTERNAL_COMMAND_AWS . ' s3 sync ' . escapeshellarg($imagesPath) . ' ' . escapeshellarg('s3://' . $s3Bucket . '/' . $s3KeyBasePath));
	}
	
	
	
	
	public static function getImagePath($imageType, $isCustom = false, $isTemp = false)
	{
		// base path
		$imagePath = CAR_IMAGE_BASE_PATH;
		
		// sub path
		if ($isTemp)
			$imagePath .= CAR_IMAGE_SUB_PATH_TEMP;
		else
		{
			if ($isCustom) $imagePath .= CAR_IMAGE_SUB_PATH_CUSTOM;
			else           $imagePath .= CAR_IMAGE_SUB_PATH;
			
			// another sub path
			if      ($imageType === CAR_IMAGE_TYPE_BASE)   $imagePath .= CAR_IMAGE_SUB_PATH_BASE;
			else if ($imageType === CAR_IMAGE_TYPE_ICON)   $imagePath .= CAR_IMAGE_SUB_PATH_ICON;
			else if ($imageType === CAR_IMAGE_TYPE_DETAIL) $imagePath .= CAR_IMAGE_SUB_PATH_DETAIL;
			else
				throw new Exception("Invalid non-temp image type for image path: $imageType");
		}
		
		return $imagePath;
	}
	
	/**
	 * Get Image Filename
	 *
	 * Generates the filename for an image.
	 * 
	 * @param string     $imageName Car's image name.
	 * @param IMAGE_TYPE $imageType S3 bucket to sync the images with.
	 * @param boolean    $isCustom  If the car is a custom added car (true) or not (false).
	 * @param boolean    $isTemp    If the filename should be for the temporary file (true) or not (false).
	 */
	public static function getImageFilename($imageName, $imageType, $isCustom = false, $isTemp = false)
	{
		// base path
		$imageFilename = self::getImagePath($imageType, $isCustom, $isTemp);
		
		// image name
		$imageFilename .= $imageName;
		
		
		// image suffix
		if      ($imageType === CAR_IMAGE_TYPE_BASE)           $imageFilename .= CAR_IMAGE_SUFFIX_BASE;
		else if ($imageType === CAR_IMAGE_TYPE_PROCESSED_BASE) $imageFilename .= CAR_IMAGE_SUFFIX_PROCESSED_BASE;
		else if ($imageType === CAR_IMAGE_TYPE_ICON)           $imageFilename .= CAR_IMAGE_SUFFIX_ICON;
		else if ($imageType === CAR_IMAGE_TYPE_DETAIL)         $imageFilename .= CAR_IMAGE_SUFFIX_DETAIL;
		else
			throw new Exception("Invalid image type for image filename: $imageType");
		
		
		// ext
		if ($isCustom) $imageFilename .= CAR_IMAGE_EXT_CUSTOM;
		else           $imageFilename .= CAR_IMAGE_EXT;
		
		if ($isTemp)
			$imageFilename  .= CAR_IMAGE_EXT_TEMP;
		
		
		return $imageFilename;
	}
	
	public static function getImageURL($imageName, $imageType, $isCustom = false)
	{
		// base URL
		$imageURL = S3_URL . '/';
		
		
		// bucket
		if ($isCustom)
			$imageURL .= S3_CAR_IMAGE_BUCKET_CUSTOM;
		else
			$imageURL .= S3_CAR_IMAGE_BUCKET;
		
		$imageURL .= '/';
		
		
		// sub path
		if      ($imageType === CAR_IMAGE_TYPE_ICON)   $imageURL .= S3_CAR_IMAGE_KEY_BASE_PATH_ICON;
		else if ($imageType === CAR_IMAGE_TYPE_DETAIL) $imageURL .= S3_CAR_IMAGE_KEY_BASE_PATH_DETAIL;
		else
			throw new Exception("Invalid image type for image url: $imageType");
		
		
		// image name
		$imageURL .= $imageName;
		
		
		// image suffix
		if ($imageType === CAR_IMAGE_TYPE_ICON) $imageURL .= CAR_IMAGE_SUFFIX_ICON;
		else                                    $imageURL .= CAR_IMAGE_SUFFIX_DETAIL;
		
		
		// ext
		if ($isCustom) $imageURL .= CAR_IMAGE_EXT_CUSTOM;
		else           $imageURL .= CAR_IMAGE_EXT;
		
		return $imageURL;
	}
}
?>