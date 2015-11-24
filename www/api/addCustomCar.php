<?php
/**
 * Adds a custom car to the database.
 * 
 * Query String:
 *  userID
 *  addToCollection - If the car should be added to the users colelction (1) or not (0).
 * 
 * Post:
 *  name - Can not be empty.
 *  segment
 *  series
 *  make
 *  color
 *  carNum
 *  customToyNumber
 *  distinguishingNotes
 *  barcodeData
 */

require_once __DIR__ . '/../../utils/httpExceptionHandler.php';
require_once __DIR__ . '/../../utils/database.php';
require_once __DIR__ . '/../../utils/imageManager.php';
require_once __DIR__ . '/../../config.php';


// make sure we got all our post data
function requiredParam($requiredParamName, $post = true)
{
	$in = $post? $_POST : $_GET;
	
	if (!isset($in[$requiredParamName]))
		throw new HTTPException(400, '"' . $requiredParamName . '" missing from ' . ($post? 'body' : 'query string'));
	
	return trim($in[$requiredParamName]);
}

$userID              = requiredParam('userID', false);
$addToCollection     = requiredParam('addToCollection', false) === '1';
$name                = requiredParam('name');
$segment             = requiredParam('segment');
$series              = requiredParam('series');
$make                = requiredParam('make');
$color               = requiredParam('color');
$carNum              = requiredParam('carNum');
$customToyNumber     = requiredParam('customToyNumber');
$distinguishingNotes = requiredParam('distinguishingNotes');
$barcodeData         = requiredParam('barcodeData');

// check post data
if (strlen($name) === 0)
	throw new HTTPException(400, 'Name can not be empty.');

if (strlen($customToyNumber) === 0)
	$customToyNumber = NULL;

if (strlen($distinguishingNotes) === 0)
	$distinguishingNotes = NULL;

if (strlen($barcodeData) === 0)
	$barcodeData = NULL;


$sortName = HotWheels2Car::createCarSortName($name);


// insert new car
$db = new DB();
$carID = $db->insertCustomCar($userID, $name, $segment, $series, $make, $color, $carNum, $sortName, $customToyNumber, $distinguishingNotes, $barcodeData);




function generateCarImageAndCopyToS3($baseFilename, $tempFilename, $newFilename, $isDetails)
{
	$type = $isDetails? 'detail' : 'icon';
	
	// generate image to temp location
	$result = ImageManager::generateCarImage($baseFilename, $tempFilename, $isDetails? CAR_IMAGE_WIDTH_DETAIL : CAR_IMAGE_WIDTH_ICON);
	
	if ($result->status !== 0 || count($result->output) > 0)
		throw new Exception("Failed to generate the $type image.\nExternal program returned non-zero status ({$result->status}) or had output:\n" . print_r($result, true));
	
	// move image to the final location
	if (!rename($tempFilename, $newFilename))
		throw new Exception("Unable to rename temp $type image file \"$tempFilename\" to \"$newFilename\".");
	
	// copy image to S3
	$result = ImageManager::copyImageToS3($newFilename, S3_CAR_IMAGE_BUCKET_CUSTOM, $isDetails? S3_CAR_IMAGE_KEY_BASE_PATH_DETAIL : S3_CAR_IMAGE_KEY_BASE_PATH_ICON);
	if ($result->status !== 0)
		throw new Exception("Failed to copy the $type image to S3.\nExternal program returned non-zero status ({$result->status}):\n" . print_r($result, true));
}


// create image name
$imageName = HotWheels2Car::createCarImageName($carID, $name);

// get filenames
$tempIconFilename   = ImageManager::getImageFilename($imageName, CAR_IMAGE_TYPE_ICON,   true, true);
$tempDetailFilename = ImageManager::getImageFilename($imageName, CAR_IMAGE_TYPE_DETAIL, true, true);

$baseFilename   = ImageManager::getImageFilename($imageName, CAR_IMAGE_TYPE_BASE,   true);
$iconFilename   = ImageManager::getImageFilename($imageName, CAR_IMAGE_TYPE_ICON,   true);
$detailFilename = ImageManager::getImageFilename($imageName, CAR_IMAGE_TYPE_DETAIL, true);


try
{
	// get image from request
	if (isset($_FILES['carPicture']))
	{
		// make sure it uploaded successfully
		$error = isset($_FILES['carPicture']['error']) ? $_FILES['carPicture']['error'] : UPLOAD_ERR_OK;
		
		if ($error !== UPLOAD_ERR_NO_FILE)
		{
			switch ($error)
			{
				case UPLOAD_ERR_OK:
					break;
				
				case UPLOAD_ERR_INI_SIZE:
				case UPLOAD_ERR_FORM_SIZE:
					throw new HTTPException(413, 'Uploaded file is too large.');
				
				case UPLOAD_ERR_PARTIAL:
					throw new HTTPException(400, 'File was only partially uploaded.');
				
				case UPLOAD_ERR_PARTIAL:
					throw new HTTPException(400, 'File was only partially uploaded.');
				
				case UPLOAD_ERR_NO_TMP_DIR:
					throw new Exception('No temporary directory for uploaded files.');
				
				case UPLOAD_ERR_CANT_WRITE:
					throw new Exception('Failed to write uploaded files to disk.');
				
				default:
					throw new Exception('Unknown upload file error: ' . $error);
			}
			
			
			// move the image to base filename
			if (move_uploaded_file($_FILES['carPicture']['tmp_name'], $baseFilename) !== true)
				throw new Exception('Failed to move uploaded file from "' . $_FILES['carPicture']['tmp_name'] . '" to "' . $baseFilename . '"');
			
			
			// generate images
			generateCarImageAndCopyToS3($baseFilename, $tempIconFilename,   $iconFilename,   false);
			generateCarImageAndCopyToS3($baseFilename, $tempDetailFilename, $detailFilename, true);
			
			// set image name
			$db->setCarImageName($carID, $imageName);
		}
	}
	
	
	if ($addToCollection)
	{
		if (!$db->setCarOwned($userID, $carID))
			throw new HTTPException(404, 'No user was not found.');
	}
	
	$db->close();
	http_response_code(201);
}
catch (Exception $e)
{
	// remove the added car
	if ($carID !== NULL)
	{
		try
		{
			$db->removeCar($carID);
		}
		catch (Exception $e2)
		{
			error_log('ERROR: Database returned an error while trying to remove car with ID "' . $carID . '" after failure: ');
			error_log($e2->getMessage());
			error_log($e2->getTraceAsString());
		}
	}
		
	// remove the images
	if (file_exists($tempIconFilename))
	{
		if (!unlink($tempIconFilename))
			error_log('WARNING: unable to delete temp icon image file "' . $tempIconFilename . '" after failure!');
	}
	
	if (file_exists($tempDetailFilename))
	{
		if (!unlink($tempDetailFilename))
			error_log('WARNING: unable to delete temp detail image file "' . $tempDetailFilename . '" after failure!');
	}
	
	if (file_exists($baseFilename))
	{
		if (!unlink($baseFilename))
			error_log('WARNING: unable to delete base image file "' . $baseFilename . '" after failure! Orphaned file.');
	}
	
	if (file_exists($iconFilename))
	{
		if (!unlink($iconFilename))
			error_log('WARNING: unable to delete icon image file "' . $iconFilename . '" after failure! Orphaned file.');
	}
	
	if (file_exists($detailFilename))
	{
		if (!unlink($detailFilename))
			error_log('WARNING: unable to delete detail image file "' . $detailFilename . '" after failure! Orphaned file.');
	}
	
	throw $e;
}
?> 
