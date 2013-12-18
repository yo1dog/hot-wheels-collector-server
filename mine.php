<?php
require 'www/includes/hotWheelsAPI.php';
require 'config.php';
require 'www/includes/database.php';

ini_set("error_log", MINE_LOG_FILE);

function clean($str)
{
	return
		str_replace('’', '\'',
		str_replace('™', '',
		str_replace('®', '', $str)));
}

function c_log($str)
{
	error_log($str);
	echo $str, "\n";
}


c_log('Mining Start');
c_log('Searching...');
$cars = HotWheelsAPI::search(' ', 300);

if (is_string($cars))
{
	c_log('Mine search failed: ' . $cars);
	die();
}

c_log('Done');

$db = new DB();

$numMined = 0;
foreach ($cars as $car)
{
	// get details
	$carDetails = HotWheelsAPI::getCarDetails($car->id);
	
	if (is_string($carDetails))
	{
		c_log('Mine getCarDetails failed for "' . $car->id . '": ' . $carDetails);
		continue;
	}
	
	// insert or update db
	try
	{
		$db->insertOrUpdateCar(
				clean($carDetails->id),
				clean($carDetails->name),
				clean($carDetails->toyNumber),
				clean($carDetails->segment),
				clean($carDetails->series),
				clean($carDetails->carNumber),
				clean($carDetails->color),
				clean($carDetails->make));
	}
	catch (Exception $e)
	{
		c_log('Mine insertOrUpdateCar failed for "' . $carDetails->id, '": ' . $e->getMessage());
	}
	
	
	// download image
	$fp = fopen(HOTWHEELS2_IMAGE_PATH . $carDetails->id . HOTWHEELS2_IMAGE_EXT, 'wb');
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,    $car->imageURL);
	curl_setopt($ch, CURLOPT_FILE,   $fp);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_exec($ch);
	
	fclose($fp);
	
	$cURLErrorNum = curl_errno($ch);
	if ($cURLErrorNum !== 0)
		c_log('Mine download image failed for "' . $carDetails->id . '": "' . $car->imageURL . '" cURL Error (' . $cURLErrorNum . '): ' . curl_error($ch));
	else
	{
		$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($statusCode !== 200)
			c_log('Mine download image failed for "' . $carDetails->id . '": "' . $car->imageURL . '" Request Error: Status code ' . $statusCode);
	}
	
	curl_close($ch);
	
	// download detail image
	$fp = fopen(HOTWHEELS2_IMAGE_PATH . $carDetails->id . HOTWHEELS2_IMAGE_DETAIL_SUFFIX . HOTWHEELS2_IMAGE_EXT, 'wb');
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,    $carDetails->detailImageURL);
	curl_setopt($ch, CURLOPT_FILE,   $fp);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_exec($ch);
	
	fclose($fp);
	
	$cURLErrorNum = curl_errno($ch);
	if ($cURLErrorNum !== 0)
		c_log('Mine download detail image failed for "' . $carDetails->id . '": "' . $carDetails->detailImageURL . '" cURL Error (' . $cURLErrorNum . '): ' . curl_error($ch));
	else
	{
		$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($statusCode !== 200)
			c_log('Mine download detail image failed for "' . $carDetails->id . '": "' . $carDetails->detailImageURL . '" Request Error: Status code ' . $statusCode);
	}
	
	curl_close($ch);
	
	// done
	++$numMined;
	echo 'Mined (', $numMined, ') "', $carDetails->id, '" - "', $carDetails->name, "\"\n";
}

$db->close();
c_log('Mining Complete');
?>
