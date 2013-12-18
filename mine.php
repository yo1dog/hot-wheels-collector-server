<?php
require 'www/includes/hotWheelsAPI.php';
require 'config.php';
require 'www/includes/database.php';

$carIDs = HotWheelsAPI::search(' ', true, 300);

if (is_string($carIDs))
	die('Mine search failed: ' . $carIDs);

$db = new DB();

$numMined = 0;
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
	
	++$numMined;
	echo 'Mined (', $numMined, ') "', $car->id, '" - "', $car->name, "\"\n";
}

$db->close();
?>