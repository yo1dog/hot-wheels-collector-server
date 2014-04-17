<?php
if (!isset($_POST['userID']))
{
	http_response_code(400);
	die('"userID" missing from POST data.');
}
if (!isset($_POST['qrCodeData']))
{
	http_response_code(400);
	die('"qrCodeData" missing from POST data.');
}
if (!isset($_POST['owned']))
{
	http_response_code(400);
	die('"owned" missing from POST data.');
}

require '../includes/qrCodeUtility.php';
require '../includes/globals.php';
require '../../config.php';
require	'../includes/hotWheels2Models.php';
require '../includes/database.php';

$userID      = $_POST['userID'];
$qrCodeData  = $_POST['qrCodeData'];
$owned       = $_POST['owned'] === '1';

try
{
	$toyNumber = QRCodeUtility::getToyNumberFromQRCode($qrCodeData);
	
	$db = new DB();
	$query = 'SELECT name, ' .
	             '(SELECT 1 FROM collections WHERE user_id = "' . $this->mysqli->real_escape_string($userID) . '" AND car_id = cars.id) AS owned ' .
	         'FROM cars WHERE toy_number = "' . $this->mysqli->real_escape_string($toyNumber) . '"';
	
	$success = $this->mysqli->real_query($query);
	if (!$success)
		throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
	
	$result = $this->mysqli->store_result();
	if ($result === false)
		throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
	
	$row = $result->fetch_row();
	
	if (!$row)
	{
		$msg = "QR code produced toy number: \"$toyNumber\" which was not found. QR code data: $qrCodeData";
		error_log($msg);
	
		throw new HTTPException(404, $msg);
	}
	
	$result->close();
	$db->close();
	
	$carName        = $row[0];
	$currentlyOwned = $row[1];
	
	$result = new stdClass();
	$result->carName      = $canName;
	$result->ownedChanged = $owned !== $currentlyOwned;
	
	if ($result->ownedChanged)
	{
		if ($owned)
		{
			if (!$db->setCarOwned($userID, $carID))
			{
				http_response_code(404);
				die('No car or user was not found.');
			}
		}
		else
			$db->setCarUnowned($userID, $carID);
	}
	
	header('Content-type: application/json');
	echo json_encode($car);
}
catch (InvalidQRCodeException $e)
{
	http_response_code(400);
	die($e->getMessage());
}

$db->close();
?>
