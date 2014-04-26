<?php
if (!isset($_GET['userID']))
{
	http_response_code(400);
	die('"userID" missing from query string.');
}

require '../includes/globals.php';
require '../../config.php';
require '../includes/hotWheels2Models.php';
require '../includes/database.php';

$userID = $_GET['userID'];
//$page = isset($_GET['page']) ? intval($_GET['page']) : 0;

$db = new DB();
//$numPages = 1;
$cars = $db->getCollection($userID);//, $page, $numPages);
$db->close();

//$response = new stdClass();
//$response->cars     = $cars;
//$response->numPages = $numPages;

header('Content-type: application/json');
echo json_encode($cars);//$response);
?>
