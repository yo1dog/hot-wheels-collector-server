<?php
if (!isset($_GET['query']))
{
	http_response_code(400);
	die('"query" missing from query string.');
}
if (!isset($_GET['userID']))
{
	http_response_code(400);
	die('"userID" missing from query string.');
}

require '../includes/globals.php';
require '../includes/database.php';

$query = $_GET['query'];
$userID = $_GET['userID'];

$db = new DB();
$cars = $db->search($query, $userID);
$db->close();

header('Content-type: application/json');
echo json_encode($cars);
?>