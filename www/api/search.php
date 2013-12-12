<?php
if (!isset($_GET['query']))
{
	http_response_code(400);
	die('"query" missing from query string.');
}

require '../includes/globals.php';
require '../includes/hotWheelsAPI.php';
require '../includes/database.php';

$query = $_GET['query'];
$result = HotWheelsAPI::search($query);

if (is_string($result))
{
	http_response_code(500);
	die($result);
}

$cars = $result;

$db = new DB();
$db->checkCarsOwned($cars);
$db->close();

header('Content-type: application/json');
echo json_encode($cars);
?>