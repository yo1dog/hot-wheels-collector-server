<?php
require '../includes/globals.php';
require '../../config.php';
require '../includes/database.php';

// make sure we got all our post data
function postParam($postParamName)
{
	if (!isset($_POST[$postParamName]))
	{
		http_response_code(400);
		die('"' . $requriedPostParam . '" missing from query string.');
	}
	
	return trim($_POST[$postParamName]);
}

$name                = postParam('name');
$segment             = postParam('segment');
$series              = postParam('series');
$make                = postParam('make');
$color               = postParam('color');
$style               = postParam('style');
$customToyNumber     = postParam('customToyNumber');
$distinguishingNotes = postParam('distinguishingNotes');
$barcodeData         = postParam('barcodeData');

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
$carID = $db->insertCustomCar($name, $segment, $series, $make, $color, $style, $sortName, $customToyNumber, $distinguishingNotes, $barcodeData);

try
{
	// create image name
	$imageName = createCarImageName($carID, $name);
	
	// set image name
	$db->setCarImageName($carID, $imageName);
	
	
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
			
			$baseFilename   = HOTWHEELS2_IMAGE_PATH . $imageName . HOTWHEELS2_IMAGE_BASE_SUFFIX   . HOTWHEELS2_IMAGE_EXT;
			$iconFilename   = HOTWHEELS2_IMAGE_PATH . $imageName . HOTWHEELS2_IMAGE_ICON_SUFFIX   . HOTWHEELS2_IMAGE_EXT;
			$detailFilename = HOTWHEELS2_IMAGE_PATH . $imageName . HOTWHEELS2_IMAGE_DETAIL_SUFFIX . HOTWHEELS2_IMAGE_EXT;
			
			if (move_uploaded_file($_FILES['carPicture']['tmp_name'], $baseFilename) !== true)
				throw new Exception('Error moving uploaded file from "' . $_FILES['carPicture']['tmp_name'] . '" to "' . $baseFilename . '"');
			
			// proccess base image
			$result = proccessCarBaseImage($baseFilename);
			if ($result !== true)
			{
				if (!unlink($baseFilename))
					error_log('WARNING: unable to delete base image file "' . $baseFilename . '" after failed hwip!');
				
				throw new Exception('Error proccessing the base image: ' . print_r($result, true));
			}
			
			// generate icon image
			$result = generateCarImage($baseFilename, $iconFilename, MINE_CAR_IMAGE_ICON_WIDTH);
			if ($result !== true)
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
			if ($result !== true)
			{
				if (file_exists($detailFilename))
				{
					if (!unlink($detailFilename))
						error_log('WARNING: unable to delete detail image file "' . $detailFilename . '" after failed convert!');
				}
				
				throw new Exception('Error proccessing the detail image: ' . print_r($result, true));
			}
		}
	}
}
catch (Exception $e)
{
	if ($carID !== NULL)
		$db->removeCar($carID);
	
	throw $e;
}
?> 