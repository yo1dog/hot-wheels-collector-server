<?php
require_once __DIR__ . '/../../utils/httpExceptionHandler.php';
require_once __DIR__ . '/../../utils/database.php';

if (!isset($_POST['userID']))
	throw new HTTPException(400, '"userID" missing from POST data.');

if (!isset($_POST['carID']))
	throw new HTTPException(400, '"carID" missing from POST data.');

$userID = $_POST['userID'];
$carID  = $_POST['carID'];

$db = new DB();
$db->setCarUnowned($userID, $carID);
$db->close();

http_response_code(204);
?>
