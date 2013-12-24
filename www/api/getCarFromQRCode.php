<?php
if (!isset($_GET['qrCodeData']))
{
	http_response_code(400);
	die('"qrCodeData" missing from query string.');
}

require '../includes/globals.php';
require '../../config.php';
require '../includes/hotWheels2Models.php';
require '../includes/database.php';

$qrCodeData = $_GET['qrCodeData'];
$userID = isset($_GET['userID']) ? $_GET['userID'] : NULL;

// make sure we the data we got is a URL
if (!substr($qrCodeData, 0, 4) === 'http')
{
	http_response_code(400);
	error_log('Received invalid QR code data: ' . $qrCodeData);
	die('Invalid QR code data: Not a URL.');
}

// hit that URL (not too hard though, just the headers)
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL,            $qrCodeData);
curl_setopt($ch, CURLOPT_TIMEOUT,        10);
curl_setopt($ch, CURLOPT_HEADER,         true);
curl_setopt($ch, CURLOPT_NOBODY,         true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// execute cURL request
$cURLResult = curl_exec($ch);

// check for cURL errors
$cURLErrorNum = curl_errno($ch);
if ($cURLErrorNum !== 0)
	throw new Exception('cURL Error (' . $cURLErrorNum . '): ' . curl_error($ch));

if ($cURLResult === false)
	throw new Exception('cURL Error: unknown');

$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ($statusCode !== 302)
{
	http_response_code(400);
	error_log('Non-302 QR data url response status code: ' . $statusCode . "\nQR code data:" . $qrCodeData);
	die('QR data URL did not return 302: Status code ' . $statusCode);
}


// parse out the location header
$index = strripos($cURLResult, 'location:');
if ($index === false)
	throw new Exception("QR code data URL response headers missing location header. Headers:\n" . $cURLResult . "\nQR code data:" . $qrCodeData);

$index += 9;

$index2r = strpos($cURLResult, "\r", $index);
$index2n = strpos($cURLResult, "\n", $index);

$index2;
if ($index2r === false)
	$index2 = $index2n;
else if ($index2n === false)
	$index2 = $index2r;
else
	$index2 = min($index2r, $index2n);

if ($index2 === false)
	throw new Exception("Error parsing QR code data URL response. Unable to find end of location header. Headers:\n" . $cURLResult . "\nQR code data:" . $qrCodeData);

$locationValue = substr($cURLResult, $index, $index2 - $index);


// parse the toy number from the location value
$index = strrpos($locationValue, '/vid/');
if ($index === false)
	throw new Exception("Error parsing QR code data URL response. \"/vid/\" missing from the location header. Location header value:\n" . $locationValue . "\nHeaders:\n" . $cURLResult . "\nQR code data:" . $qrCodeData);

$index += 5;

$index2 = strpos($locationValue, '?', $index);
if ($index2 === false)
	$index2 = strlen($locationValue);

$toyNumber = rtrim(substr($locationValue, $index, $index2 - $index));

if (strlen($toyNumber) === 0)
	throw new Exception("Error parsing QR code data URL response. Toy number in location header is empty. Location header value:\n" . $locationValue . "\nHeaders:\n" . $cURLResult . "\nQR code data:" . $qrCodeData);


$db = new DB();
$car = $db->getCarByToyNumber(strtoupper($toyNumber), $userID);
$db->close();

if ($car === NULL)
{
	http_response_code(404);
	die('QR code produced toy number: "' . $toyNumber . '" which was not found.');
}

header('Content-type: application/json');
echo json_encode($car);
?>
