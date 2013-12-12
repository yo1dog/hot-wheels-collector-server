<?php
require '../includes/config.php';
require '../includes/hotWheelsAPI.php';
require '../includes/database.php';

$db = new DB();
$carIDs = $db->getCarsOwned();
$db->close();

$cars = array();
foreach ($carIDs as $carID)
{
	$result = HotWheelsAPI::getCarDetails($carID);
	
	if (is_string($result))
		$cars[] = $result;
	else
	{
		$car = $result;
		
		$car->owned = true;
		$cars[] = $car;
	}
}

header('Content-type: application/json');
echo json_encode($cars);
?>