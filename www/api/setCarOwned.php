<?php
if (!isset($_POST['carID']))
{
	http_response_code(400);
	die('"carID" missing from POST data.');
}
if (!isset($_POST['owned']))
{
	http_response_code(400);
	die('"owned" missing from POST data.');
}

require '../includes/config.php';
require '../includes/database.php';

$carID = $_POST['carID'];
$owned = $_POST['owned'] === '1';

$db = new DB();
$db->setCarOwned($carID, $owned);
$db->close();
?>