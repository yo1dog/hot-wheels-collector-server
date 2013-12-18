<?php
require 'www/includes/hotWheelsAPI.php';
require 'config.php';
require 'www/includes/database.php';

echo "Searching...\n";
$cars = HotWheelsAPI::search('lotus', 300);

if (is_string($cars))
	die('Mine search failed: ' . $cars . "\n");

echo "Done\n";

$db = new DB();

$numMined = 0;
foreach ($cars as $car)
{
	// get details
	$carDetails = HotWheelsAPI::getCarDetails($car->id);
	
	if (is_string($carDetails))
	{
		echo 'Mine getCarDetails failed for "', $car->id, '": ', $carDetails, "\n";
		continue;
	}
	
	// insert or update db
	try
	{
		$db->insertOrUpdateCar($carDetails->id, $carDetails->name, $carDetails->toyNumber, $carDetails->segment, $carDetails->series, $carDetails->carNumber, $carDetails->color, $carDetails->make);
	}
	catch (Exception $e)
	{
		echo 'Mine insertOrUpdateCar failed for "', $carDetails->id, '": ', $e->getMessage(), "\n";
	}
	
	
	// download image
	$fp = fopen(HOTWHEELS2_IMAGE_PATH . $carDetails->id . HOTWHEELS2_IMAGE_EXT, 'wb');
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,    $car->imageURL);
	curl_setopt($ch, CURLOPT_FILE,   $fp);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_exec($ch);
	curl_close($ch);
	
	fclose($fp);
	
	$cURLErrorNum = curl_errno($ch);
	if ($cURLErrorNum !== 0)
		echo 'Mine download image cURL Error (' . $cURLErrorNum . '): ' . curl_error($ch), "\n";
	
	$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	if ($statusCode !== 200)
		echo 'Mine download image Request Error: Status code ' . $statusCode, "\n";
	
	
	// download detail image
	$fp = fopen(HOTWHEELS2_IMAGE_PATH . $carDetails->id . HOTWHEELS2_IMAGE_DETAIL_SUFFIX . HOTWHEELS2_IMAGE_EXT, 'wb');
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,    $carDetails->detailImageURL);
	curl_setopt($ch, CURLOPT_FILE,   $fp);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_exec($ch);
	curl_close($ch);
	
	fclose($fp);
	
	$cURLErrorNum = curl_errno($ch);
	if ($cURLErrorNum !== 0)
		echo 'Mine download detail image cURL Error (' . $cURLErrorNum . '): ' . curl_error($ch), "\n";
	
	$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	if ($statusCode !== 200)
		echo 'Mine download detail image Request Error: Status code ' . $statusCode, "\n";
	
	// done
	++$numMined;
	echo 'Mined (', $numMined, ') "', $carDetails->id, '" - "', $carDetails->name, "\"\n";
}

$db->close();
?>