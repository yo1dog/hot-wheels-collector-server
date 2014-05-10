<?php
require_once __DIR__ . '/../../utils/httpExceptionHandler.php';
require_once __DIR__ . '/../../utils/database.php';

if (!isset($_GET['userID']))
	throw new HTTPException(400, '"userID" missing from query string.');

$userID = $_GET['userID'];

$db = new DB();
$cars = $db->getCollectionRemovals($userID);
$db->close();

header('Content-type: application/json');
echo json_encode($cars);
?>