<?php
if (!isset($_POST['userID']))
{
	http_response_code(400);
	die('"userID" missing from POST data.');
}
if (!isset($_POST['carID']))
{
	http_response_code(400);
	die('"carID" missing from POST data.');
}

require '../includes/globals.php';
require '../../config.php';
require	'../includes/hotWheels2Models.php';
require '../includes/database.php';

$userID = $_POST['userID'];
$carID  = $_POST['carID'];

$db = new DB();

$timestamp;
$alreadyOwned;
if (!$db->setCarOwned($userID, $carID, $timestamp, $alreadyOwned))
{
	http_response_code(404);
	die('No car or user was not found.');
}

$db->close();

$response = new stdClass();
$response->ownedTimestmap = $timestamp;
$response->alreadyOwned = $alreadyOwned;

header('Content-type: application/json');
echo json_encode($response);
?>
