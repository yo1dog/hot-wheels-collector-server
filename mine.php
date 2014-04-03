<?php
$usage = <<<EOT
php mine.php <flags>

-g -regenerateImages     : Re-generate and replace the icon and detail images from the
                           base images even if they already exist.
                           Without this flag we only generate icon and detail images if
                           they do not exist.

-h -help                 : Display this usage help text.

-k -skipImages           : Skip images completely. Do not check for needed images,
                           download base images, nor generate icon and detail images.
                           Without this flag we download base images and generate icon
                           and detail images as needed.

-l -log <filename>       : Use the given filename to log PHP errors to.
                           Without this flag, MINE_LOG_FILE fom config.php is used.

-r -redownloadBaseImages : Download and replace base images, even if they already exist.
                           Base images are used to generate the icon and detail images.
                           Without this flag we only download base images if they do not
                           exist.

-s -search <search term> : Uses the given search term to search the Hot Wheels site to
                           limit the responses.
                           Without this flag a single space " " is used which returns
                           every car on the Hot Wheels site.

-v -verbose              : If you don't know what a verbsose flag is... oh my.

EOT;

require 'config.php';
require 'www/includes/globals.php';
require 'www/includes/hotWheelsAPI.php';
require 'www/includes/database.php';


/*****************************************************************************************************
 *****************************************************************************************************
 * Helper Functions
 * 
 *****************************************************************************************************
 *****************************************************************************************************/

$verbose = false;

// handles printing verbose and non-verbose output
function c_print($str, $isVerbose = false)
{
	global $verbose;
	if ($isVerbose && !$verbose)
		return;
	
	echo $str, "\n";
}

// handles printing an external execution's failure
function printExternalFailure($result)
{
	c_print("ERROR: external program returned non-zero status ({$result['status']}) or had output:");
	c_print('----------');
	c_print($result['cmd']);
	
	foreach ($result['output'] as $line)
		c_print($line);
	
	c_print('----------');
}

// transforms a car into an identifiable string
function carToString($car)
{
	return (isset($car->id) ? "\"{$car->id}\" - " : '') . "\"{$car->vehicleID}\" - \"{$car->toyNumber}\" - \"{$car->name}\"";
}

// cleans a field returned from the Hot Wheels website
function cleanField($str)
{
	if (strlen($str) === 0)
		return $str;
	
	return
		str_replace('’', '\'',
		str_replace('™', '',
		str_replace('®', '', $str)));
}

// formats a duration of seconds into a human-readable string
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




/*****************************************************************************************************
 *****************************************************************************************************
 * Main Functions
 *
 *****************************************************************************************************
 *****************************************************************************************************/

/*****************************************************************************************************
 * Get Cars
 * 
 * Takes a list of detail URLs and uses the HotWheels2API to parse a car out of each detail page.
 * Also returns a list of detail URLs that fail.
 */
function getCars($detailURLs, $db, &$cars)
{
	$numCarsUpdated = 0;
	$numCarsAdded = 0;
	$detailURLsFailed = array();
	
	$detailURLNum = 0;
	$first = true;
	$nonVerboseOutputLastIteration = false;
	foreach ($detailURLs as $detailURL)
	{
		++$detailURLNum;
		
		if ($first)
			$first = false;
		else
			c_print('', !$nonVerboseOutputLastIteration);
		
		$nonVerboseOutputLastIteration = false;
		
		
		c_print("Trying detail URL ($detailURLNum): \"$detailURL\"", true);
		
		if (strlen($detailURL) === 0)
		{
			c_print("WARNING: Detail URL is empty, skipping: \"$detailURL\"");
			$nonVerboseOutputLastIteration = true;
			continue;
		}
		
		
		
		// get car from details URL
		$result = HotWheelsAPI::getCar($detailURL);
		
		if (is_string($result))
		{
			c_print("ERROR: HotWheels2API returned an error for detail URL: \"$detailURL\"");
			c_print($result);
			
			$detailURLsFailed[] = $detailURL;
			$nonVerboseOutputLastIteration = true;
			continue;
		}
		
		$car = $result;
		
		
		
		// cleanField the fields
		$car->vehicleID = strtolower($car->vehicleID);
		$car->name      = cleanField($car->name);
		$car->toyNumber = strtoupper(cleanField($car->toyNumber));
		$car->segment   = cleanField($car->segment);
		$car->series    = cleanField($car->series);
		$car->make      = cleanField($car->make);
		$car->color     = cleanField($car->color);
		$car->style     = cleanField($car->style);
		
		// add sortname
		$car->sortName  = createCarSortName($car->name);
		
		c_print('Found car: ' . carToString($car), true);
		
		
		
		// insert or update db
		$car->id = NULL;
		try
		{
			$added = false;
			$updated = false;
			$updatedFields = NULL;
			
			$car->id = $db->insertOrUpdateCar($car, $added, $updated, $updatedFields);
			
			$cars[] = $car;
			
			if ($added)
			{
				$car->imageName = createCarImageName($car->id, $car->name);
				$db->setCarImageName($car->id, $car->imageName);
				
				c_print('New car added: ' . carToString($car));
				++$numCarsAdded;
			}
			else
			{
				$car->imageName = $db->getCarImageName($car->id);
				
				if ($updated)
				{
					c_print('Car updated: ' . carToString($car));
					c_print('Fields updated:');
					
					foreach ($updatedFields as $fieldName => $update)
					{
						c_print($fieldName);
						c_print('from ' . $update['from']);
						c_print('to   ' . $update['to']);
					}
					
					++$numCarsUpdated;
				}
			}
		}
		catch (Exception $e)
		{
			c_print('ERROR: Database returned an error during insertOrUpdateCar or setCarImageName: ' . carToString($car));
			c_print($e->getMessage());
			
			$detailURLsFailed[] = $detailURL;
			$nonVerboseOutputLastIteration = true;
			
			if ($car->id !== NULL)
			{
				try
				{
					$db->removeCar($car->id);
				}
				catch (Exception $e)
				{
					c_print("ERROR: Database returned an error while trying to remove car with ID \"{$car->id}\" after a previous database error: " . carToString($car));
					c_print($e->getMessage());
				}
			}
			continue;
		}
	}
	
	$result = new stdClass;
	$result->numCarsUpdated   = $numCarsUpdated;
	$result->numCarsAdded     = $numCarsAdded;
	$result->detailURLsFailed = $detailURLsFailed;
	
	return $result;
}




/*****************************************************************************************************
 * Download Image
 *
 * Downlaods a single image.
 */

function downloadImage($filename, $url)
{
	$fp = NULL;
	$ch = NULL;
	
	try
	{
		$fp = fopen($filename, 'wb');
		
		if ($fp === false)
			throw new Exception('Unable to open file.');
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,    $url);
		curl_setopt($ch, CURLOPT_FILE,   $fp);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_exec($ch);
		
		fclose($fp);
		$fp = NULL;
		
		$cURLErrorNum = curl_errno($ch);
		if ($cURLErrorNum !== 0)
			throw new Exception("cURL Error ($cURLErrorNum): " . curl_error($ch));
		
		$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($statusCode !== 200)
			throw new Exception("Non-200 resposne status code: $statusCode");
		
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




/*****************************************************************************************************
 * Generate Car Image Type
 *
 * Generates an image with the given width from a processed base image.
 */

function generateCarImageType($baseProcessedFilename, $newFilename, $width, $type, $car)
{
	if (file_exists($iconFilename))
	{
		if (!unlink($iconFilename))
			c_print("WARNING: Unable to delete existing $type image file before image generation: \"$newFilename\"");
	}
	
	$result = generateCarImage($baseProcessedFilename, $newFilename, $width);
	
	if ($result === true)
		return true;
	
	c_print("ERROR: Failed to generate $type image for car: " . carToString($car));
	printExternalFailure($result);
		
	if (file_exists($newFilename))
	{
		if (!unlink($newFilename))
			c_print("WARNING: Unable to delete $type image file after failed image generation: \"$newFilename\"");
	}
	
	return false;
}




/*****************************************************************************************************
 * Get Car Images
 *
 * Takes an array of cars and downloads base images and generates icon and detail images.
 */

function getCarImages($cars, $regenerateImages, $redownloadBaseImages)
{
	$baseImageDownloadFailedForCars = array();
	
	$numCarsGeneratedImagesFor = 0;
	$numBaseImagesDownloaded = 0;
	$numBaseImagesProcessed = 0;
	$numBaseImageProcessingsFailed = 0;
	$numImagesGenerated = 0;
	$numImageGenerationsFailed = 0;
	
	$first = true;
	$nonVerboseOutputLastIteration = false;
	
	foreach ($cars as $car)
	{
		if ($first)
			$first = false;
		else
			c_print('', !$nonVerboseOutputLastIteration);
		
		$nonVerboseOutputLastIteration = false;
		
		$baseFilename          = HOTWHEELS2_IMAGE_PATH . $car->imageName . HOTWHEELS2_IMAGE_BASE_SUFFIX           . HOTWHEELS2_IMAGE_EXT;
		$baseProcessedFilename = HOTWHEELS2_IMAGE_PATH . $car->imageName . HOTWHEELS2_IMAGE_BASE_PROCESSED_SUFFIX . HOTWHEELS2_IMAGE_EXT;
		$iconFilename          = HOTWHEELS2_IMAGE_PATH . $car->imageName . HOTWHEELS2_IMAGE_ICON_SUFFIX           . HOTWHEELS2_IMAGE_EXT;
		$detailFilename        = HOTWHEELS2_IMAGE_PATH . $car->imageName . HOTWHEELS2_IMAGE_DETAIL_SUFFIX         . HOTWHEELS2_IMAGE_EXT;
		
		
		
		// downlaod base image
		if ($redownloadBaseImages || !file_exists($baseFilename))
		{
			$url = $car->getImageURL(MINE_CAR_IMAGE_BASE_WIDTH);
			
			c_print("Downloading base image \"$url\" for car: " . carToString($car));
			
			if (file_exists($baseFilename))
			{
				if (!unlink($baseFilename))
					c_print("WARNING: Unable to delete existing base image file before re-download: \"$baseFilename\"");
			}
			
			try
			{
				downloadImage($baseFilename, $url);
			}
			catch (Exception $e)
			{
				c_print("ERROR: download image failed for URL: \"$url\"");
				c_print($e->getMessage);
				
				if (file_exists($baseFilename))
				{
					if (!unlink($baseFilename))
						c_print("WARNING: unable to delete base image file after failed download: \"$baseFilename\"");
				}
				
				$baseImageDownloadFailedForCars[] = $car;
				continue;
			}
			
			++$numBaseImagesDownloaded;
		}
		
		
		
		// don't process if we are not regenerating images and both images already exist
		if (!$regenerateImages && file_exists($iconFilename) && file_exists($detailFilename))
			continue;
		
		++$numCarsGeneratedImagesFor;
		c_print('Generating images for car: ' . carToString($car), true);
		
		
		// process base image with HWIP
		if (file_exists($baseProcessedFilename))
		{
			if (!unlink($baseProcessedFilename))
				c_print("WARNING: unable to delete existing base processed image file before processing: \"$baseProcessedFilename\"");
		}
		
		$result = proccessCarBaseImage($baseFilename, $baseProcessedFilename);
		
		if ($result !== true)
		{
			c_print("ERROR: Failed to proccess base image for car: " . carToString($car));
			printExternalFailure($result);
		
			++$numBaseImageProcessingsFailed;
			
			if (file_exists($baseProcessedFilename))
			{
				if (!unlink($baseProcessedFilename))
					c_print("WARNING: unable to delete base processed image file after failed processing: \"$baseProcessedFilename\"");
			}
		
			continue;
		}
		
		++$numBaseImagesProcessed;
		
		
		
		// generate images
		if (generateCarImageType($baseProcessedFilename, $iconFilename  , MINE_CAR_IMAGE_ICON_WIDTH  , 'icon'))
			++$numImagesGenerated;
		else
			++$numImageGenerationsFailed;
		
		if (generateCarImageType($baseProcessedFilename, $detailFilename, MINE_CAR_IMAGE_DETAIL_WIDTH, 'detail'))
			++$numImagesGenerated;
		else
			++$numImageGenerationsFailed;
	}
	
	$result = new stdClass();
	$result->downloadFailedForCars   = $downloadFailedForCars;
	$result->numCarsUpdating         = $numCarsUpdating;
	$result->numImagesDownloaded     = $numImagesDownloaded;
	$result->numUpdatedImages        = $numUpdatedImages;
	$result->numUpdateImagesFailed   = $numUpdateImagesFailed;
	
	return $result;
}






/*****************************************************************************************************
 *****************************************************************************************************
 * Main
 *
 *****************************************************************************************************
 *****************************************************************************************************/

$skipImages = false;
$regenerateImages = false;
$redownloadBaseImages = false;
$searchTerm = ' ';
$logFilename = MINE_LOG_FILE;

for ($i = 0; $i < count($argv); ++$i)
{
	switch ($argv[$i])
	{
		case '-h':
		case '-help':
			die($usageHelp);
		
		case '-v':
		case '-verbose':
			$verbose = true;
			break;
		
		case '-k':
		case '-skipImages':
			$skipImages = true;
			break;
		
		case '-g':
		case '-regenerateImages':
			$regenerateImages = true;
			break;
		
		case '-r':
		case '-redownloadBaseImages':
			$redownloadBaseImages = true;
			break;
		
		case '-s':
		case '-search':
			++$i;
			
			if ($i > count($argv))
				die("ERROR: -serach flag used without a search term following it.\nUse -help flag for usage help.");
			
			$searchTerm = $argv[$i];
			
			if (strlen($searchTerm) === 0 || $searchTerm[0] === '-')
				die("ERROR: -serach flag used without a search term following it.\nUse -help flag for usage help.");
			
			break;
		
		case '-l':
		case '-log':
			++$i;
			
			if ($i > count($argv))
				die("ERROR: -log flag used without a filename following it.\nUse -help flag for usage help.");
			
			$logFilename = $argv[$i];
			
			if (strlen($logFilename) === 0 || $logFilename[0] === '-')
				die("ERROR: -log flag used without a filename following it.\nUse -help flag for usage help.");
			
			break;
	}
}

if ($skipImages && $regenerateImages)
	die("ERROR: Cannot use -skipImages and -regenerateImages flags together. This does not make sense.\nUse -help flag for usage help.");

if ($skipImages && $redownloadBaseImages)
	die("ERROR: Cannot use -skipImages and -redownloadBaseImages flags together. This does not make sense.\nUse -help flag for usage help.");

ini_set("error_log", $logFilename);

c_print('******************************************************************************************');
c_print('******************************************************************************************', true);
c_print('* Mining Start');
c_print('******************************************************************************************');
c_print('******************************************************************************************', true);
c_print('', true);

if ($skipImages)
  c_print('* Skipping images');

if ($regenerateImages)
  c_print('* Regenerating icon and detail images');

if ($redownloadBaseImages)
  c_print('* Re-downloading base images');

c_print('');
c_print('Searching' . ($searchTerm === ' ' ? '' : ' "' . $searchTerm . '"') . '...');

$startTime = time();
$detailURLs = HotWheelsAPI::search($searchTerm, 300);
$endTime = time();

if (is_string($detailURLs))
{
	c_print('ERROR: Search failed: ' . $detailURLs);
	die();
}

$numDetailURLs = count($detailURLs);
c_print('Done. Found ' . $numDetailURLs . ' detail URLs');
c_print('Took ' . formatDuration($endTime - $startTime));

$db = new DB();

c_print('');
c_print('*********************************************', true);
c_print('* Mining Car Details');
c_print('*********************************************', true);
c_print('', true);

$cars = array();

$startTime = time();
$result = getCars($detailURLs, $db, $cars);
$endTime = time();

$numCarsUpdated   = $result->numCarsUpdated;
$numCarsAdded     = $result->numCarsAdded;
$detailURLsFailed = $result->detailURLsFailed;

c_print('');
c_print('*********************************************', true);
c_print('', true);

$numDetailURLsFailed = count($detailURLsFailed);
$numCars = count($cars);
$numDetailURLsTried = $numCars + $numDetailURLsFailed;

c_print('Took ' . formatDuration($endTime - $startTime));
c_print($numDetailURLsTried . ' detail URLs tried');
c_print($numDetailURLsFailed . ' detail URLs failed');
c_print(($numDetailURLs - $numDetailURLsTried) . ' detail URLs skipped');
c_print($numCars . ' cars found');
c_print($numCarsUpdated . ' cars updated');
c_print($numCarsAdded . ' cars added');

if ($numDetailURLsTried === 0)
{
	c_print('');
	c_print('ERROR: No detail URLs were tried. Search may have returned no results or all detail URLs returned may have been empty.');
	die();
}

if ($numDetailURLsFailed > 0)
{
	c_print('');
	
	if ($numDetailURLsFailed > $numDetailURLsTried * 0.25 && $numDetailURLsFailed > 20)
		c_print($numDetailURLsFailed . ' detail URLs failed. This is more than 1/4th of the total detail URLs tried (' . $numDetailURLsTried . ') and more than 20 and will not rety.');
	else
	{
		c_print($numDetailURLsFailed . ' detail URLs failed. Retrying those in 10 seconds...');
		c_print('');
		c_print('*********************************************', true);
		c_print('', true);
		
		sleep(10);
		
		$startTime = time();
		$result = getCars($detailURLsFailed, $db, $cars);
		$endTime = time();
		
		$numCarsUpdated   = $result->numCarsUpdated;
		$numCarsAdded     = $result->numCarsAdded;
		$detailURLsFailed = $result->detailURLsFailed;
		
		c_print('');
		c_print('*********************************************', true);
		c_print('', true);
		
		$numDetailURLs = $numDetailURLsFailed;
		$numDetailURLsFailed = count($detailURLsFailed);
		$numCars = count($cars) - $numCars;
		$numDetailURLsTried = $numCars + $numDetailURLsFailed;
		
		c_print('Took ' . formatDuration($endTime - $startTime));
		c_print($numDetailURLsTried . ' detail URLs tried');
		c_print($numDetailURLsFailed . ' detail URLs failed');
		c_print(($numDetailURLs - $numDetailURLsTried) . ' detail URLs skipped');
		c_print($numCars . ' cars found');
		c_print($numCarsUpdated . ' cars updated');
		c_print($numCarsAdded . ' cars added');
	}
}

if (!$skipImages)
{
	c_print('');
	c_print('*********************************************', true);
	c_print('* Mining Images');
	c_print('*********************************************', true);
	c_print('', true);
	
	$startTime = time();
	$result = getCarImages($cars, $regenerateImages, $redownloadBaseImages);
	$endTime = time();

	$numImageDownloadsFailed = count($result->downloadFailedForCars);
	
	c_print('');
	c_print('*********************************************', true);
	c_print('', true);
	
	c_print('Took ' . formatDuration($endTime - $startTime));
	c_print($result->numCarsUpdating . ' cars updated');
	c_print((count($cars) - $result->numCarsUpdating) . ' cars skipped');
	c_print($result->numImagesDownloaded     . ' images downloaded');
	c_print($numImageDownloadsFailed . ' image downloads failed');
	c_print($result->numUpdatedImages        . ' images updated');
	c_print($result->numUpdateImagesFailed   . ' image updates failed');
	
	if ($numImageDownloadsFailed > 0)
	{
		c_print('');
		
		$numImageDownloadsTried = $numImageDownloadsFailed + $result->numImagesDownloaded;
		
		if ($numImageDownloadsFailed > $numImageDownloadsTried * 0.25 && $numImageDownloadsFailed > 20)
			c_print($numImageDownloadsFailed . ' image downloads failed. This is more than 1/4th of the total image downloads tried (' . $numImageDownloadsTried . ') and more than 20 and will not rety.');
		else
		{
			c_print($numImageDownloadsFailed . ' image downloads failed. Retrying those in 10 seconds...');
			c_print('');
			c_print('*********************************************', true);
			c_print('', true);
	
			sleep(10);
			
			$startTime = time();
			$result = getCarImages($result->downloadFailedForCars, $regenerateImages, $redownloadBaseImages);
			$endTime = time();
			
			$numImageDownloadsFailedOld = $numImageDownloadsFailed;
			$numImageDownloadsFailed = count($result->downloadFailedForCars);
			
			c_print('');
			c_print('*********************************************', true);
			c_print('', true);
			
			c_print('Took ' . formatDuration($endTime - $startTime));
			c_print($result->numCarsUpdating . ' cars updated');
			c_print(($numImageDownloadsFailedOld - $result->numCarsUpdating) . ' cars skipped');
			c_print($result->numImagesDownloaded     . ' images downloaded');
			c_print($numImageDownloadsFailed . ' image downloads failed');
			c_print($result->numUpdatedImages        . ' images updated');
			c_print($result->numUpdateImagesFailed   . ' image updates failed');
		}
	}
}

$db->close();

c_print('');
c_print('*********************************************', true);
c_print('Mining Complete');
c_print('*********************************************', true);
?>
