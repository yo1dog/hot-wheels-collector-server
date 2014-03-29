<?php
/*
php mine.php searchTerm(optinal) flags

searchTerm - (Optional) will use the given term to search the Hot Wheels site instead of " " which returns all cars

flags:
skipImages           - will not download or update any images
updateExistingImages - will re-generate all icon and detail images even if they already exist.
redownloadBaseImages - will download the base images used to generate the icon and detail images even if it already exists

php mine.php corvette updateExistingImages
*/

require 'config.php';
require 'www/includes/globals.php';
require 'www/includes/hotWheelsAPI.php';
require 'www/includes/database.php';

ini_set("error_log", MINE_LOG_FILE);

function c_log($str)
{
	error_log($str);
	echo $str, "\n";
}

function logExternalFailure($result)
{
	c_log('ERROR: ' . $result['logName'] . ' returned non-zero status (' . $result['status'] . ') or had output');
	c_log('----------');
	c_log($result['cmd']);
	
	foreach ($result['output'] as $line)
		c_log($line);
	
	c_log('----------');
}

function clean($str)
{
	if (strlen($str) === 0)
		return $str;
	
	return
		str_replace('’', '\'',
		str_replace('™', '',
		str_replace('®', '', $str)));
}

function formatDuration($seconds)
{
	$mins  = floor($seconds / 60);
	$hours = floor($mins / 60);
	$days  = floor($hours / 24);
	
	$seconds = $seconds % 60;
	$mins    = $mins % 60;
	$hours   = $hours % 24;
	
	$str = '';
	
	if ($days > 0)
		$str .= $days . 'd';
	if ($hours > 0)
	{
		if (strlen($str) > 0)
			$str .= ' ';
		
		$str .= $hours . 'h';
	}
	if ($mins > 0)
	{
		if (strlen($str) > 0)
			$str .= ' ';
		
		$str .= $mins . 'm';
	}
	
	if ($seconds > 0)
	{
		if (strlen($str) > 0)
			$str .= ' ';
		
		$str .= $seconds . 's';
	}
	
	if (strlen($str) === 0)
		return '0s';
	
	return $str;
}

function getCars($detailURLs, $db, &$cars)
{
	$numCarsUpdated = 0;
	$numCarsAdded = 0;
	$detailURLsFailed = array();
	
	$detailURLNum = 0;
	$first = true;
	foreach ($detailURLs as $detailURL)
	{
		++$detailURLNum;
		
		if ($first)
			$first = false;
		else
			echo "\n";
		
		echo "Trying detail URL ($detailURLNum): $detailURL\n";
		
		if (strlen($detailURL) === 0)
		{
			c_log('empty detail URL');
			continue;
		}
		
		// get car from details URL
		$car = HotWheelsAPI::getCar($detailURL);
		
		if (is_string($car))
		{
			c_log('getCar failed for "' . $detailURL . '": ' . $car);
			
			$detailURLsFailed[] = $detailURL;
			continue;
		}
		
		// clean the fields
		$car->vehicleID = strtolower($car->vehicleID);
		$car->name      = clean($car->name);
		$car->toyNumber = strtoupper(clean($car->toyNumber));
		$car->segment   = clean($car->segment);
		$car->series    = clean($car->series);
		$car->make      = clean($car->make);
		$car->color     = clean($car->color);
		$car->style     = clean($car->style);
		
		// add sortname
		$car->sortName  = createCarSortName($car->name);
		
		echo "Found car \"{$car->vehicleID}\" - \"{$car->toyNumber}\" - \"{$car->name}\"\n";
		
		// insert or update db
		try
		{
			$carID;
			$result = $db->insertOrUpdateCar($car, $carID);
			
			$cars[] = $car;
			
			if ($result === 2)
			{
				$car->imageName = createCarImageName($carID, $car->name);
				$db->setCarImageName($carID, $car->imageName);
				
				++$numCarsAdded;
			}
			else
			{
				$car->imageName = $db->getCarImageName($carID);
				
				if ($result === 1)
					++$numCarsUpdated;
			}
		}
		catch (Exception $e)
		{
			c_log('insertOrUpdateCar failed for "' . $car->vehicleID . '": ' . $e->getMessage());
			
			$detailURLsFailed[] = $detailURL;
			continue;
		}
	}
	
	$result = new stdClass;
	$result->numCarsUpdated   = $numCarsUpdated;
	$result->numCarsAdded     = $numCarsAdded;
	$result->detailURLsFailed = $detailURLsFailed;
	
	return $result;
}




function downloadImage($filename, $url)
{
	$fp = NULL;
	$ch = NULL;
	
	try
	{
		$fp = fopen($filename, 'wb');
		
		if ($fp === false)
			throw new Exception('"' . $filename . '" unable to open file');
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,    $url);
		curl_setopt($ch, CURLOPT_FILE,   $fp);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_exec($ch);
		
		fclose($fp);
		$fp = NULL;
		
		$cURLErrorNum = curl_errno($ch);
		if ($cURLErrorNum !== 0)
			throw new Exception('cURL Error (' . $cURLErrorNum . '): ' . curl_error($ch));
		
		$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($statusCode !== 200)
			throw new Exception('Request Error: Status code ' . $statusCode);
		
		curl_close($ch);
		$ch = NULL;
	}
	catch(Exception $e)
	{
		if ($fp)
			fclose($fp);
		if ($ch)
			curl_close($ch);
		
		throw $e;
	}
}

function updateCarImages($cars, $updateExistingImages, $redownloadBaseImages)
{
	$downloadFailedForCars = array();
	
	$numCarsUpdating = 0;
	$numImagesDownloaded = 0;
	$numUpdatedImages = 0;
	$numUpdateImagesFailed = 0;
	
	$first = true;
	foreach ($cars as $car)
	{
		if ($first)
			$first = false;
		else
			echo "\n";
			
		$baseFilename   = HOTWHEELS2_IMAGE_PATH . $car->imageName . HOTWHEELS2_IMAGE_BASE_SUFFIX   . HOTWHEELS2_IMAGE_EXT;
		$iconFilename   = HOTWHEELS2_IMAGE_PATH . $car->imageName . HOTWHEELS2_IMAGE_ICON_SUFFIX   . HOTWHEELS2_IMAGE_EXT;
		$detailFilename = HOTWHEELS2_IMAGE_PATH . $car->imageName . HOTWHEELS2_IMAGE_DETAIL_SUFFIX . HOTWHEELS2_IMAGE_EXT;
		
		// check if they exist already
		if ($updateExistingImages || !file_exists($iconFilename) || !file_exists($detailFilename))
		{
			++$numCarsUpdating;
			echo  "Updating images for \"{$car->vehicleID}\" - \"{$car->toyNumber}\" - \"{$car->name}\"\n";
			
			// check if base image already exists
			if ($redownloadBaseImages || !file_exists($baseFilename))
			{
				$url = $car->getImageURL(MINE_CAR_IMAGE_BASE_WIDTH);
				
				c_log('Downloading base image: ' . $url);
				
				if (file_exists($baseFilename))
				{
					if (!unlink($baseFilename))
						c_log('WARNING: unable to delete existing base image file "' . $baseFilename . '" before re-download!');
				}
				
				try
				{
					downloadImage($baseFilename, $url);
				}
				catch (Exception $e)
				{
					c_log('ERROR: download image failed for URL "' . $url . '": ' . $e->getMessage());
					
					if (file_exists($baseFilename))
					{
						if (!unlink($baseFilename))
							c_log('WARNING: unable to delete base image file "' . $baseFilename . '" after failed download!');
					}
					
					$downloadFailedForCars[] = $car;
					continue;
				}
				
				++$numImagesDownloaded;
				
				// trim background with hwip
				$result = proccessCarBaseImage($baseFilename);
				if ($result !== true)
				{
					logExternalFailure($result);
		
					++$numUpdateImagesFailed;
					
					if (!unlink($baseFilename))
						c_log('WARNING: unable to delete base image file "' . $baseFilename . '" after failed hwip!');
					
					continue;
				}
				
				++$numUpdatedImages;
			}
			
			// generate images
			if (file_exists($iconFilename))
			{
				if (!unlink($iconFilename))
					c_log('WARNING: unable to delete existing icon image file "' . $iconFilename . '" before update!');
			}
			
			$result = generateCarImage($baseFilename, $iconFilename, MINE_CAR_IMAGE_ICON_WIDTH);
			if ($result !== true)
			{
				logExternalFailure($result);
				
				++$numUpdateImagesFailed;
				
				if (file_exists($iconFilename))
				{
					if (!unlink($iconFilename))
						c_log('WARNING: unable to delete icon image file "' . $iconFilename . '" after failed convert!');
				}
			}
			else
				++$numUpdatedImages;
			
			
			if (file_exists($detailFilename))
			{
				if (!unlink($detailFilename))
					c_log('WARNING: unable to delete existing detail image file "' . $detailFilename . '" before update!');
			}
			
			$result = generateCarImage($baseFilename, $detailFilename, MINE_CAR_IMAGE_DETAIL_WIDTH);
			if ($result !== true)
			{
				logExternalFailure($result);
				
				++$numUpdateImagesFailed;
				
				if (file_exists($detailFilename))
				{
					if (!unlink($detailFilename))
						c_log('WARNING: unable to delete detail image file "' . $detailFilename . '" after failed convert!');
				}
			}
			else
				++$numUpdatedImages;
		}
	}
	
	$result = new stdClass();
	$result->downloadFailedForCars   = $downloadFailedForCars;
	$result->numCarsUpdating         = $numCarsUpdating;
	$result->numImagesDownloaded     = $numImagesDownloaded;
	$result->numUpdatedImages        = $numUpdatedImages;
	$result->numUpdateImagesFailed   = $numUpdateImagesFailed;
	
	return $result;
}

$skipImages = false;
$updateExistingImages = false;
$redownloadBaseImages = false;
$searchTerm = ' ';

foreach ($argv as $arg)
{
	if ($arg === 'skipImages')
		$skipImages = true;
	
	else if ($arg === 'updateExistingImages')
		$updateExistingImages = true;
	
	else if ($arg === 'redownloadBaseImages')
		$redownloadBaseImages = true;
}

if ($skipImages && $updateExistingImages)
{
	c_log('ERROR: Cannot use skipImages and updateExistingImages flags together. Does not make sense.');
	die();
}

if ($skipImages && $redownloadBaseImages)
{
	c_log('ERROR: Cannot use skipImages and redownloadBaseImages flags together. Does not make sense.');
	die();
}

if (isset($argv[1]) && $argv[1] !== 'skipImages' && $argv[1] !== 'updateExistingImages' && $argv[1] !== 'redownloadBaseImages')
	$searchTerm = $argv[1];

c_log('******************************************************************************************');
c_log('******************************************************************************************');
c_log('* Mining Start');
c_log('******************************************************************************************');
c_log('******************************************************************************************');
c_log('');

if ($skipImages)
  c_log('* Skipping images');

if ($updateExistingImages)
  c_log('* Updating existing images');

if ($redownloadBaseImages)
  c_log('* Re-downloading base images');

c_log('');
c_log('Searching' . ($searchTerm === ' ' ? '' : ' "' . $searchTerm . '"') . '...');

$startTime = time();
$detailURLs = HotWheelsAPI::search($searchTerm, 300);
$endTime = time();

if (is_string($detailURLs))
{
	c_log('ERROR: Search failed: ' . $detailURLs);
	die();
}

$numDetailURLs = count($detailURLs);
c_log('Done. Found ' . $numDetailURLs . ' detail URLs');
c_log('Took ' . formatDuration($endTime - $startTime));

$db = new DB();

c_log('');
c_log('*********************************************');
c_log('* Mining Car Details');
c_log('*********************************************');
c_log('');

$cars = array();

$startTime = time();
$result = getCars($detailURLs, $db, $cars);
$endTime = time();

$numCarsUpdated   = $result->numCarsUpdated;
$numCarsAdded     = $result->numCarsAdded;
$detailURLsFailed = $result->detailURLsFailed;

c_log('');
c_log('*********************************************');
c_log('');

$numDetailURLsFailed = count($detailURLsFailed);
$numCars = count($cars);
$numDetailURLsTried = $numCars + $numDetailURLsFailed;

c_log('Took ' . formatDuration($endTime - $startTime));
c_log($numDetailURLsTried . ' detail URLs tried');
c_log($numDetailURLsFailed . ' detail URLs failed');
c_log(($numDetailURLs - $numDetailURLsTried) . ' detail URLs skipped');
c_log($numCars . ' cars found');
c_log($numCarsUpdated . ' cars updated');
c_log($numCarsAdded . ' cars added');

if ($numDetailURLsTried === 0)
{
	c_log('');
	c_log('ERROR: No detail URLs were tried. Search may have returned no results or all detail URLs returned may have been empty.');
	die();
}

if ($numDetailURLsFailed > 0)
{
	c_log('');
	
	if ($numDetailURLsFailed > $numDetailURLsTried * 0.25 && $numDetailURLsFailed > 20)
		c_log($numDetailURLsFailed . ' detail URLs failed. This is more than 1/4th of the total detail URLs tried (' . $numDetailURLsTried . ') and more than 20 and will not rety.');
	else
	{
		c_log($numDetailURLsFailed . ' detail URLs failed. Retrying those in 10 seconds...');
		c_log('');
		c_log('*********************************************');
		c_log('');
		
		sleep(10);
		
		$startTime = time();
		$result = getCars($detailURLsFailed, $db, $cars);
		$endTime = time();
		
		$numCarsUpdated   = $result->numCarsUpdated;
		$numCarsAdded     = $result->numCarsAdded;
		$detailURLsFailed = $result->detailURLsFailed;
		
		c_log('');
		c_log('*********************************************');
		c_log('');
		
		$numDetailURLs = $numDetailURLsFailed;
		$numDetailURLsFailed = count($detailURLsFailed);
		$numCars = count($cars) - $numCars;
		$numDetailURLsTried = $numCars + $numDetailURLsFailed;
		
		c_log('Took ' . formatDuration($endTime - $startTime));
		c_log($numDetailURLsTried . ' detail URLs tried');
		c_log($numDetailURLsFailed . ' detail URLs failed');
		c_log(($numDetailURLs - $numDetailURLsTried) . ' detail URLs skipped');
		c_log($numCars . ' cars found');
		c_log($numCarsUpdated . ' cars updated');
		c_log($numCarsAdded . ' cars added');
	}
}

if (!$skipImages)
{
	c_log('');
	c_log('*********************************************');
	c_log('* Mining Images');
	c_log('*********************************************');
	c_log('');
	
	$startTime = time();
	$result = updateCarImages($cars, $updateExistingImages, $redownloadBaseImages);
	$endTime = time();

	$numImageDownloadsFailed = count($result->downloadFailedForCars);
	
	c_log('');
	c_log('*********************************************');
	c_log('');
	
	c_log('Took ' . formatDuration($endTime - $startTime));
	c_log($result->numCarsUpdating . ' cars updated');
	c_log((count($cars) - $result->numCarsUpdating) . ' cars skipped');
	c_log($result->numImagesDownloaded     . ' images downloaded');
	c_log($numImageDownloadsFailed . ' image downloads failed');
	c_log($result->numUpdatedImages        . ' images updated');
	c_log($result->numUpdateImagesFailed   . ' image updates failed');
	
	if ($numImageDownloadsFailed > 0)
	{
		c_log('');
		
		$numImageDownloadsTried = $numImageDownloadsFailed + $result->numImagesDownloaded;
		
		if ($numImageDownloadsFailed > $numImageDownloadsTried * 0.25 && $numImageDownloadsFailed > 20)
			c_log($numImageDownloadsFailed . ' image downloads failed. This is more than 1/4th of the total image downloads tried (' . $numImageDownloadsTried . ') and more than 20 and will not rety.');
		else
		{
			c_log($numImageDownloadsFailed . ' image downloads failed. Retrying those in 10 seconds...');
			c_log('');
			c_log('*********************************************');
			c_log('');
	
			sleep(10);
			
			$startTime = time();
			$result = updateCarImages($result->downloadFailedForCars, $updateExistingImages, $redownloadBaseImages);
			$endTime = time();
			
			$numImageDownloadsFailedOld = $numImageDownloadsFailed;
			$numImageDownloadsFailed = count($result->downloadFailedForCars);
			
			c_log('');
			c_log('*********************************************');
			c_log('');
			
			c_log('Took ' . formatDuration($endTime - $startTime));
			c_log($result->numCarsUpdating . ' cars updated');
			c_log(($numImageDownloadsFailedOld - $result->numCarsUpdating) . ' cars skipped');
			c_log($result->numImagesDownloaded     . ' images downloaded');
			c_log($numImageDownloadsFailed . ' image downloads failed');
			c_log($result->numUpdatedImages        . ' images updated');
			c_log($result->numUpdateImagesFailed   . ' image updates failed');
		}
	}
}

$db->close();

c_log('');
c_log('*********************************************');
c_log('Mining Complete');
c_log('*********************************************');
?>
