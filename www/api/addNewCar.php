<?php
// make sure we got all our post data
$requriedPostParams = array
(
	'name',
	'segment',
	'series',
	'make',
	'color',
	'style',
	'customToyNumber',
	'distinguishingNotes'
);

foreach ($requriedPostParams as $requriedPostParam)
{
	if (!isset($_POST[$requriedPostParam]))
	{
		http_response_code(400);
		die('"' . $requriedPostParam . '" missing from query string.');
	}
}

// check post data
if (strlen($_POST['name']) === 0)
{
	http_response_code(400);
	die('Name can not be empty.');
}



// insert new car
require '../includes/globals.php';
require '../../config.php';
require '../includes/database.php';
$db = new DB();

$db->insertCustomCar($_POST['name'], $_POST['customToyNumber'], $_POST['segment'], $_POST['series'], $_POST['make'], $_POST['color'], $_POST['style'], $_POST['distinguishingNotes']);



// upload picture
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
				throw new Exception('Unknown upload file error ($_FILES[$filename][\'error\']: ' . $error . ')');
				break;
		}
		
		
		echo $_FILES['carPicture']['tmp_name'];
	}
}
?> 