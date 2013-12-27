<?php
if (!isset($_GET['query']))
{
	http_response_code(400);
	die('"query" missing from query string.');
}

require '../includes/globals.php';
require '../../config.php';
require	'../includes/hotWheels2Models.php';
require '../includes/database.php';

$query = $_GET['query'];
$userID = isset($_GET['userID']) ? $_GET['userID'] : NULL;

$db = new DB();
$cars = $db->search($query, $userID);

if ($cars === NULL)
{
	http_response_code(400);
	die('Invalid query. Nothing to search.');
}

// try toy number
if (count($cars) === 0)
{
	$car = $db->getCarByToyNumber(strtoupper(trim($query)), $userID);
	
	if ($car !== NULL)
		$cars[] = $car;
}

$db->close();

header('Content-type: application/json');
echo json_encode($cars);
?>
