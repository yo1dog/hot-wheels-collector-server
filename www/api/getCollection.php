<?php
require_once __DIR__ . '/../../utils/httpExceptionHandler.php';
require_once __DIR__ . '/../../utils/database.php';

if (!isset($_GET['userID']))
	throw new HTTPException(400, '"userID" missing from query string.');

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
