<?php
if (!isset($_GET['carID']))
{
	http_response_code(400);
	die('"carID" missing from query string.');
}

require '../includes/config.php';
require '../includes/hotWheelsAPI.php';
require '../includes/database.php';

$carID = $_GET['carID'];
$result = HotWheelsAPI::getCarDetails($carID);

if (is_string($result))
{
	http_response_code(500);
	die($result);
}

$car = $result;

$db = new DB();
$db->checkCarsOwned(array($car));
$db->close();

header('Content-type: application/json');
echo json_encode($car);
?>