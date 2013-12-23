<?php
if (!isset($_GET['userID']))
{
	http_response_code(400);
	die('"userID" missing from query string.');
}

require '../includes/globals.php';
require '../../config.php';
require	'../includes/hotWheels2Models.php';
require '../includes/database.php';

$userID = $_GET['userID'];

$db = new DB();
$cars = $db->getCollectionRemovals($userID);
$db->close();

header('Content-type: application/json');
echo json_encode($cars);
?>