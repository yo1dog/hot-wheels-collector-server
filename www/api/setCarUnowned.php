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
$db->setCarUnowned($userID, $carID);
$db->close();

http_response_code(204);
?>
