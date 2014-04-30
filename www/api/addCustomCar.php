<?php
require '../includes/globals.php';
require '../../config.php';
require '../includes/database.php';

// make sure we got all our post data
function requiredParam($requiredParamName, $post = true)
{
	$in = $post? $_POST : $_GET;
	
	if (!isset($in[$requiredParamName]))
	{
		http_response_code(400);
		die('"' . $requiredParamName . '" missing from ' . ($post? 'body' : 'query string'));
	}
	
	return trim($in[$requiredParamName]);
}

$userID              = requiredParam('userID', false);
$addToCollection     = requiredParam('addToCollection', false) === '1';
$name                = requiredParam('name');
$segment             = requiredParam('segment');
$series              = requiredParam('series');
$make                = requiredParam('make');
$color               = requiredParam('color');
$style               = requiredParam('style');
$customToyNumber     = requiredParam('customToyNumber');
$distinguishingNotes = requiredParam('distinguishingNotes');
$barcodeData         = requiredParam('barcodeData');

// check post data
if (strlen($name) === 0)
{
	http_response_code(400);
	die('Name can not be empty.');
}

if (strlen($customToyNumber) === 0)
	$customToyNumber = NULL;

if (strlen($distinguishingNotes) === 0)
	$distinguishingNotes = NULL;

if (strlen($barcodeData) === 0)
	$barcodeData = NULL;

$sortName = createCarSortName($name);


// insert new car
$db = new DB();
$carID = $db->insertCustomCar($userID, $name, $segment, $series, $make, $color, $style, $sortName, $customToyNumber, $distinguishingNotes, $barcodeData);

try
{
	// create image name
	$imageName = createCarImageName($carID, $name);
	
	
	// moved uploaded picture
	if (isset($_FILES['carPicture']))
	{
		$error = isset($_FILES['carPicture']['error']) ? $_FILES['carPicture']['error'] : UPLOAD_ERR_OK;
		
		if ($error !== UPLOAD_ERR_NO_FILE)
		{
			switch ($error)
			{
				case UPLOAD_ERR_OK:
					break;
				
				case UPLOAD_ERR_INI_SIZE:
				case UPLOAD_ERR_FORM_SIZE:
					http_response_code(413);
					die('Uploaded file is too large.');
					break;
				
				case UPLOAD_ERR_PARTIAL:
					http_response_code(400);
					die('File was only partially uploaded.');
					break;
				
				case UPLOAD_ERR_PARTIAL:
					http_response_code(400);
					die('File was only partially uploaded.');
					break;
				
				case UPLOAD_ERR_NO_TMP_DIR:
					throw new Exception('No temporary directory for uploaded files.');
					break;
				
				case UPLOAD_ERR_CANT_WRITE:
					throw new Exception('Failed to write uploaded files to disk.');
					break;
				
				default:
					throw new Exception('Unknown upload file error: ' . $error);
					break;
			}
			
			$baseFilename   = HOTWHEELS2_IMAGE_PATH . HOTWHEELS2_IMAGE_BASE_DIR   . $imageName . HOTWHEELS2_IMAGE_BASE_SUFFIX   . HOTWHEELS2_IMAGE_CUSTOM_EXT;
			$iconFilename   = HOTWHEELS2_IMAGE_PATH . HOTWHEELS2_IMAGE_ICON_DIR   . $imageName . HOTWHEELS2_IMAGE_ICON_SUFFIX   . HOTWHEELS2_IMAGE_CUSTOM_EXT;
			$detailFilename = HOTWHEELS2_IMAGE_PATH . HOTWHEELS2_IMAGE_DETAIL_DIR . $imageName . HOTWHEELS2_IMAGE_DETAIL_SUFFIX . HOTWHEELS2_IMAGE_CUSTOM_EXT;
			
			if (move_uploaded_file($_FILES['carPicture']['tmp_name'], $baseFilename) !== true)
				throw new Exception('Error moving uploaded file from "' . $_FILES['carPicture']['tmp_name'] . '" to "' . $baseFilename . '"');
			
			// generate icon image
			$result = generateCarImage($baseFilename, $iconFilename, MINE_CAR_IMAGE_ICON_WIDTH);
			if ($result->status !== 0 || count($result->output) > 0)
			{
				if (file_exists($iconFilename))
				{
					if (!unlink($iconFilename))
						error_log('WARNING: unable to delete icon image file "' . $iconFilename . '" after failed convert!');
				}
				
				throw new Exception('Error proccessing the icon image: ' . print_r($result, true));
			}
			
			// generate detail image
			$result = generateCarImage($baseFilename, $detailFilename, MINE_CAR_IMAGE_DETAIL_WIDTH);
			if ($result->status !== 0 || count($result->output) > 0)
			{
				if (file_exists($detailFilename))
				{
					if (!unlink($detailFilename))
						error_log('WARNING: unable to delete detail image file "' . $detailFilename . '" after failed convert!');
				}
				
				throw new Exception('Error proccessing the detail image: ' . print_r($result, true));
			}
			
			// set image name
			$db->setCarImageName($carID, $imageName);
		}
	}
	
	
	if ($addToCollection)
	{
		if (!$db->setCarOwned($userID, $carID))
		{
			http_response_code(404);
			die('No car or user was not found.');
		}
	}
	
	http_response_code(201);
}
catch (Exception $e)
{
	if ($carID !== NULL)
		$db->removeCar($carID);
	
	throw $e;
}
?> 
