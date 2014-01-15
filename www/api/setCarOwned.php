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
if (!isset($_POST['owned']))
{
	http_response_code(400);
	die('"owned" missing from POST data.');
}

require '../includes/globals.php';
require '../../config.php';
require	'../includes/hotWheels2Models.php';
require '../includes/database.php';

$userID = $_POST['userID'];
$carID  = $_POST['carID'];
$owned  = $_POST['owned'] === '1';

$db = new DB();

if ($owned)
	$db->setCarOwned($userID, $carID);
else
	$db->setCarUnowned($userID, $carID);

$db->close();
?>
