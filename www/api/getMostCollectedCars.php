<?php
require '../includes/globals.php';
require '../../config.php';
require	'../includes/hotWheels2Models.php';
require '../includes/database.php';

$userID = isset($_GET['userID']) ? $_GET['userID'] : NULL;

$db = new DB();
$cars = $db->getMostCollectedCars($userID);
$db->close();

header('Content-type: application/json');
echo json_encode($cars);
?>