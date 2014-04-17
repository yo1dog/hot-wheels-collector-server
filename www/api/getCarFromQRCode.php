<?php
if (!isset($_GET['qrCodeData']))
{
	http_response_code(400);
	die('"qrCodeData" missing from query string.');
}

require '../includes/qrCodeUtility.php';
require '../includes/globals.php';
require '../../config.php';
require '../includes/hotWheels2Models.php';
require '../includes/database.php';

$qrCodeData = $_GET['qrCodeData'];
$userID = isset($_GET['userID']) ? $_GET['userID'] : NULL;

try
{
	$toyNumber = QRCodeUtility::getToyNumberFromQRCode($qrCodeData);
	
	$db = new DB();
	$car = $db->getCarByToyNumber(strtoupper($toyNumber), $userID);
	$db->close();
	
	if ($car === NULL)
	{
		$msg = "QR code produced toy number: \"$toyNumber\" which was not found. QR code data: $qrCodeData";
		error_log($msg);
		
		throw new HTTPException(404, $msg);
	}
	
	header('Content-type: application/json');
	echo json_encode($car);
}
catch (InvalidQRCodeException $e)
{
	http_response_code(400);
	die($e->getMessage());
}
?>
