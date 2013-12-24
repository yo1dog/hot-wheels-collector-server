<?php
require 'www/includes/hotWheelsAPI.php';
require 'config.php';
require 'www/includes/database.php';

ini_set("error_log", MINE_LOG_FILE);

function clean($str)
{
	if (strlen($str) === 0)
		return $str;
	
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

function downloadImage($filename, $url, $id, $imgType)
{
	$fp = NULL;
	$ch = NULL;
	$result = 0;
	
	try
	{
		$fp = fopen($filename, 'wb');
		
		if ($fp === false)
		{
			c_log('Download ' . $imgType . ' image failed for "' . $id . '": "' . $filename . '" unable to open file');
			return 0;
		}
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,    $url);
		curl_setopt($ch, CURLOPT_FILE,   $fp);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_exec($ch);
		
		fclose($fp);
		$fp = NULL;
		
		$cURLErrorNum = curl_errno($ch);
		if ($cURLErrorNum !== 0)
		{
			c_log('Download ' . $imgType . ' image failed for "' . $id . '": "' . $url . '" cURL Error (' . $cURLErrorNum . '): ' . curl_error($ch));
			$result = 2;
		}
		else
		{
			$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			
			if ($statusCode !== 200)
			{
				c_log('Download ' . $imgType . ' image failed for "' . $id . '": "' . $url . '" Request Error: Status code ' . $statusCode);
				$result = 2;
			}
			else
				$result = 1;
		}
		
		curl_close($ch);
		$ch = NULL;
	}
	catch(Exception $e)
	{
		if ($fp)
			fclose($fp);
		if ($ch)
			curl_close($ch);
		
		c_log('Download ' . $imgType . ' image failed for "' . $id . '": ' . $e->getMessage());
	}
	
	return $result;
}


c_log('Mining Start');
c_log('Searching...');
$cars = HotWheelsAPI::search(' ', 300);

if (is_string($cars))
{
	c_log('Search failed: ' . $cars);
	die();
}

c_log('Done');

$db = new DB();

$numMined = 0;
foreach ($cars as $car)
{
	if (strlen($car->id) === 0)
		continue;
	
	// get details
	$carDetails = HotWheelsAPI::getCarDetails($car->id);
	
	if (is_string($carDetails))
	{
		c_log('getCarDetails failed for "' . $car->id . '": ' . $carDetails);
		continue;
	}
	
	$imageName = preg_replace('/[^a-zA-Z0-9]/', '_', $carDetails->id);
	
	// create sortname
	$sortName = strtolower($carDetails->name);
	$sortName = preg_replace('/[^a-z0-9 ]/', '', $sortName);
	
	if (preg_match('/^[0-9]+s/', $sortName))
	{
		$index = strpos($sortName, 's');
		$sortName = substr($sortName, 0, $index) . substr($sortName, $index + 1);
	}
	
	$sortName = str_replace(' ', '', $sortName);
	
	$matches;
	if (preg_match('/^[0-9]+/', $sortName, $matches))
	{
		if (count($matches) > 0)
		{
			$yearStr = $matches[0];			
			$sortName = substr($sortName, strlen($yearStr)) . ' ' . $yearStr;
		}
	}
	
	
	// insert or update db
	try
	{
		$name = clean($carDetails->name);
		
		$ascii0 = ord($name[0]);
		$ascii1 = ord($name[1]);
		if ($ascii0 > 47 && $ascii0 < 58 &&
			$ascii1 > 47 && $ascii1 < 58)
		{
			if ($name[2] === '\'' && $name[3] !== 's')
				$name = '\'' . substr($name, 0, 2) . substr($str, 3);
		}
		
		$db->insertOrUpdateCar(
				$carDetails->id,
				$name,
				strtoupper(clean($carDetails->toyNumber)),
				clean($carDetails->segment),
				clean($carDetails->series),
				clean($carDetails->carNumber),
				clean($carDetails->color),
				clean($carDetails->make),
				$carDetails->numUsersCollected,
				$imageName,
				$sortName);
	}
	catch (Exception $e)
	{
		c_log('insertOrUpdateCar failed for "' . $carDetails->id . '": ' . $e->getMessage());
	}
	
	
	// download image
	$filename = HOTWHEELS2_IMAGE_PATH . $imageName . HOTWHEELS2_IMAGE_EXT;
	
	if (downloadImage($filename, $carDetails->imageURL, $carDetails->id, 'icon') === 2)
	{
		// try downloading and using the hover image
		if (downloadImage($filename, substr($carDetails->imageURL, 0, -4) . '_hover.png', $carDetails->id, 'icon hover') === 1)
			c_log('Succesfully downloaded the hover image as backup for "' . $carDetails->id . '"');
	}
	
	// download detail image
	$filename = HOTWHEELS2_IMAGE_PATH . $imageName . HOTWHEELS2_IMAGE_DETAIL_SUFFIX . HOTWHEELS2_IMAGE_EXT;
	downloadImage($filename, $carDetails->detailImageURL, $carDetails->id, 'detail');
	
	// done
	++$numMined;
	echo 'Mined (', $numMined, ') "', $carDetails->id, '" - "', $carDetails->name, "\"\n";
}

$db->close();
c_log('Mining Complete');
?>
