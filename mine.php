<?php
require 'www/includes/hotWheelsAPI.php';
require 'www/includes/database.php';

$carIDs = HotWheelsAPI::search(' ', true, 300);

if (is_string($carIDs))
	die('Mine search failed: ' . $carIDs);

$db = new DB();

foreach ($carIDs as $carID)
{
	$car = HotWheelsAPI::getCarDetails($carID);
	
	if (is_string($carIDs))
	{
		echo 'Mine getCarDetails failed for "', $carID, '": ', $car, "\n";
		continue;
	}
	
	try
	{
		$db->insertOrUpdateCar($car->id, $car->name, $car->toyNumber, $car->segment, $car->series, $car->carNumber, $car->color, $car->make);
	}
	catch (Exception $e)
	{
		echo 'Mine insertOrUpdateCar failed for "', $carID, '": ', $e->message, "\n";
	}
}

$db->close();
?>