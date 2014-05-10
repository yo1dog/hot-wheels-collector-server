<?php
require_once __DIR__ . '/../../utils/database.php';

$userID = isset($_GET['userID']) ? $_GET['userID'] : NULL;

$db = new DB();
$cars = $db->getMostCollectedCars($userID);
$db->close();

header('Content-type: application/json');
echo json_encode($cars);
?>