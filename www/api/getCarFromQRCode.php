<?php
require_once __DIR__ . '/../../utils/httpExceptionHandler.php';
require_once __DIR__ . '/../../utils/qrCodeTranslator.php';
require_once __DIR__ . '/../../utils/database.php';

if (!isset($_GET['qrCodeData']))
	throw new HTTPException(400, '"qrCodeData" missing from query string.');

$qrCodeData = $_GET['qrCodeData'];
$userID = isset($_GET['userID']) ? $_GET['userID'] : NULL;

try
{
	$toyNumber = QRCodeTranslator::getToyNumberFromQRCode($qrCodeData);
	
	$db = new DB();
	$car = $db->getCarByToyNumber(strtoupper($toyNumber), $userID);
	$db->close();
	
	if ($car === NULL)
	{
		$msg = "QR code produced toy number: \"$toyNumber\" which was not found. QR code data:\n$qrCodeData";
		error_log($msg);
		
		throw new HTTPException(404, $msg);
	}
	
	header('Content-type: application/json');
	echo json_encode($car);
}
catch (InvalidQRCodeException $e)
{
	throw new HTTPException(400, $e->getMessage());
}
?>
