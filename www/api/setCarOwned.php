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

$timestamp = NULL;
$alreadyOwned = NULL;
if (!$db->setCarOwned($userID, $carID, $timestamp, $alreadyOwned))
	throw new HTTPException(404, 'No car or user was not found.');

$db->close();

$response = new stdClass();
$response->ownedTimestmap = $timestamp;
$response->alreadyOwned = $alreadyOwned;

header('Content-type: application/json');
echo json_encode($response);
?>
