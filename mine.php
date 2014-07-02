<?php
$usageHelp = <<<EOT
php mine.php <flags>

-d -redownloadBaseImages   : Download and replace base images, even if they already exist.
                             Base images are used to generate the icon and detail images.
                             Without this flag we only download base images if they do not
                             exist.

-g -regenerateImages       : Regenerate and replace the icon and detail images from the
                             base images even if they already exist.
                             Without this flag we only generate icon and detail images if
                             they do not exist.

-h -help                   : Display this usage help text.

-k -skipImages             : Skip images completely. Do not check for needed images,
                             download base images, nor generate icon and detail images.
                             Without this flag we download base images and generate icon
                             and detail images as needed.

-l -log <filename>         : Use the given filename to log PHP errors to.
                             Without this flag, MINE_LOG_FILE fom config.php is used.

-q -quiet                  : Only output if errors or changes occured.
                             Wihtout this flag, we always output.

-s -search <search term>   : Uses the given search term to search the Hot Wheels site to
                             limit the responses.
                             Without this flag a single space " " is used which returns
                             every car on the Hot Wheels site.

-v -verbose                : If you don't know what a verbsose flag is... oh my.

-y -skipS3Sync             : Skip syncing icon and detail images with S3.
                             Without this flag, icon and detail images are synced with S3
                             after images are generated.

EOT;


function customErrorHandler($errno, $errstr, $errfile, $errline )
{
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}
set_error_handler("customErrorHandler");

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils/hotWheelsAPI.php';
require_once __DIR__ . '/utils/database.php';
require_once __DIR__ . '/utils/hotWheels2Car.php';
require_once __DIR__ . '/utils/imageManager.php';


/*****************************************************************************************************
 *****************************************************************************************************
 * Helper Functions
 * 
 *****************************************************************************************************
 *****************************************************************************************************/

$quietMode = false;
$verbose = false;
$shouldOutput = false;
$explicitOutputLength = 0;

$carsAdded = array();
$carsUpdated = array();


// handles printing verbose and non-verbose output
/**
 * @param string  $str 
 * @param boolean $isVerbose 
 */
function c_print($str, $isVerbose = false)
{
	global $verbose, $quietMode, $explicitOutputLength;
	
	if ($isVerbose && !$verbose)
		return;
	
	error_log($str);
	
	$str .= "\n";
	echo $str;
	
	$explicitOutputLength += strlen($str);
}

// handles printing an external execution's failure
function printExternalResult(ExternalProcessResult $result)
{
	c_print('----------');
	c_print($result->cmd);
	
	foreach ($result->output as $line)
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

// formats text into a table
function printTable($rows, $verbose = false)
{
	$maxLength = 0;
	foreach ($rows as $row)
	{
		if ($row === NULL)
			continue;
		
		$maxLength = max(strlen($row[0]), $maxLength);
	}
	
	foreach ($rows as $row)
	{
		$rowString = '';
		
		if ($row !== NULL)
		{
			$rowString .= $row[0];
			
			for ($i = strlen($rowString); $i < $maxLength; ++$i)
				$rowString .= ' ';
			
			$rowString .= $row[1];
		}
		
		c_print($rowString, $verbose);
	}
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
	global $carsAdded, $carsUpdated;
	
	$detailURLsFailed   = array();
	$numDetailURLsTried = 0;
	
	$numCarsFound     = 0;
	$numCarsSucceeded = 0;
	$numCarsUpdated   = 0;
	$numCarsAdded     = 0;
	
	$detailURLNum = 0;
	$verboseOutputLastIteration    = false;
	$nonVerboseOutputLastIteration = false;
	
	foreach ($detailURLs as $detailURL)
	{
		++$detailURLNum;
		
		if ($verboseOutputLastIteration || $nonVerboseOutputLastIteration)
			c_print('', !$nonVerboseOutputLastIteration);
		
		$verboseOutputLastIteration    = false;
		$nonVerboseOutputLastIteration = false;
		
		
		if (strlen($detailURL) === 0)
		{
			c_print("WARNING: Detail URL is empty, skipping: \"$detailURL\"");
			$nonVerboseOutputLastIteration = true;
			continue;
		}
		
		
		// get car from details URL
		c_print("Trying detail URL ($detailURLNum): \"$detailURL\"", true);
		$verboseOutputLastIteration = true;
		
		++$numDetailURLsTried;
		
		$car = NULL;
		try
		{
			$car = HotWheelsAPI::getCar($detailURL);
		}
		catch (Exception $e)
		{
			c_print("ERROR: HotWheels2API returned an error while getting a car for detail URL: \"$detailURL\"");
			c_print($e->getMessage());
			$nonVerboseOutputLastIteration = true;
			
			$detailURLsFailed[] = $detailURL;
			continue;
		}
		
		
		
		// clean the fields
		$car->vehicleID = strtolower($car->vehicleID);
		$car->name      = cleanField($car->name);
		$car->toyNumber = strtoupper(cleanField($car->toyNumber));
		$car->segment   = cleanField($car->segment);
		$car->series    = cleanField($car->series);
		$car->make      = cleanField($car->make);
		$car->color     = cleanField($car->color);
		$car->style     = cleanField($car->style);
		
		// add sortname
		$car->sortName  = HotWheels2Car::createCarSortName($car->name);
		
		c_print('Found car: ' . carToString($car), true);
		++$numCarsFound;
		
		
		// insert or update db
		$car->id = NULL;
		$added = false;
		try
		{
			if (!$db->mysqli->autocommit(false))
				throw new Exception("Unable to set autocommit to false.\nMySQL Error ({$this->mysqli->errno}): {$this->mysqli->error}");
				
			try
			{
				$updated = false;
				$updatedFields = NULL;
			
				try
				{
					$car->id = $db->insertOrUpdateCar($car, $added, $updated, $updatedFields);
				}
				catch (Exception $e)
				{
					$e->__from = "insertOrUpdateCar";
					throw $e;
				}
			
				if ($added)
				{
					$car->imageName = HotWheels2Car::createCarImageName($car->id, $car->name);
				
					try
					{
						$db->setCarImageName($car->id, $car->imageName);
					}
					catch (Exception $e)
					{
						$e->__from = "setCarImageName";
						throw $e;
					}
				
					c_print('New car added: ' . carToString($car));
					$carsAdded[] = $car;
					++$numCarsAdded;
				}
				else
				{
					try
					{
						$car->imageName = $db->getCarImageName($car->id);
					}
					catch (Exception $e)
					{
						$e->__from = "getCarImageName";
						throw $e;
					}
				
					if ($updated)
					{
						// ignore id and numUsersCollected
						if (isset($updatedFields['id']))
							unset($updatedFields['id']);
						if (isset($updatedFields['numUsersCollected']))
							unset($updatedFields['numUsersCollected']);
					
						// only count as updated if there are still fields left
						if (count($updatedFields) > 0)
						{
							c_print('Car updated: ' . carToString($car));
							c_print('Fields updated:');
	
							foreach ($updatedFields as $fieldName => $update)
							{
								c_print($fieldName);
								c_print('  from: ' . $update['from']);
								c_print('  to  : ' . $update['to']);
							}
						
							$updatedCar = new stdClass();
							$updatedCar->updatedFields = $updatedFields;
							$updatedCar->car = $car;
						
							$carsUpdated[] = $updatedCar;
						
							++$numCarsUpdated;
						}
					}
				}
			}
			catch (Exception $e)
			{
				if (!$db->mysqli->rollback())
					throw new Exception("Unable to rollback.\nMySQL Error ({$this->mysqli->errno}): {$this->mysqli->error}", 0, $e);
			
				throw $e;
			}
			
			if (!$db->mysqli->commit())
				throw new Exception("Unable to commit.\nMySQL Error ({$this->mysqli->errno}): {$this->mysqli->error}");
		}
		catch (Exception $e)
		{
			if (!$db->mysqli->autocommit(true))
				throw new Exception("Unable to set autocommit to true.\nMySQL Error ({$this->mysqli->errno}): {$this->mysqli->error}", 0, $e);
			
			$previousException = $e->getPrevious();
			$from = NULL;
			if (isset($e->__from))
				$from = $e->__from;
			else if ($previousException !== NULL && isset($previousException->__from))
				$from = $previousException->__from;
			else
				$from = 'insert or update phase';
			
			c_print("ERROR: Database returned an error during $from: " . carToString($car));
			c_print($e->__toString());
			$nonVerboseOutputLastIteration = true;
			
			continue;
		}
		
		if (!$db->mysqli->autocommit(true))
			throw new Exception("Unable to set autocommit to true.\nMySQL Error ({$this->mysqli->errno}): {$this->mysqli->error}");
		
		++$numCarsSucceeded;
		$cars[] = $car;
	}
	
	$result = new stdClass;
	$result->detailURLsFailed       = $detailURLsFailed;
	
	$result->numDetailURLsTried     = $numDetailURLsTried;
	$result->numDetailURLsSucceeded = $numDetailURLsTried - count($detailURLsFailed);
	$result->numDetailURLsFailed    = count($detailURLsFailed);
	$result->numDetailURLsSkipped   = count($detailURLs) - $numDetailURLsTried;
	
	$result->numCarsFound     = $numCarsFound;
	$result->numCarsSucceeded = $numCarsSucceeded;
	$result->numCarsFailed    = $numCarsFound - $numCarsSucceeded;
	$result->numCarsUpdated   = $numCarsUpdated;
	$result->numCarsAdded     = $numCarsAdded;
	$result->numCarsNoChange  = $numCarsSucceeded - $numCarsUpdated - $numCarsAdded;
	
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
 * Download Car Base Images
 *
 * Takes an array of cars and downloads base images for each.
 */

function downloadCarBaseImages($cars, $redownloadBaseImages)
{
	$retryBaseImageDownloadsForCars = array();
	
	$numBaseImageDownloadsTried     = 0;
	$numBaseImageDownloadsSucceeded = 0;
	
	$verboseOutputLastIteration    = false;
	$nonVerboseOutputLastIteration = false;
	
	foreach ($cars as $car)
	{
		if ($verboseOutputLastIteration || $nonVerboseOutputLastIteration)
			c_print('', !$nonVerboseOutputLastIteration);
		
		$verboseOutputLastIteration    = false;
		$nonVerboseOutputLastIteration = false;
		
		
		if (!isset($car->imageName) || $car->imageName === NULL || strlen($car->imageName) === 0)
		{
			c_print('WARNING: No imageName for car, skipping: ' . carToString($car));
			$nonVerboseOutputLastIteration = true;
			
			continue;
		}
		
		
		$tempBaseFilename = ImageManager::getImageFilename($car->imageName, CAR_IMAGE_TYPE_BASE, false, true);
		$baseFilename     = ImageManager::getImageFilename($car->imageName, CAR_IMAGE_TYPE_BASE);
		
		// downlaod base image to temp
		if ($redownloadBaseImages || !file_exists($baseFilename))
		{
			++$numBaseImageDownloadsTried;
			
			$url = $car->getImageURL(CAR_IMAGE_WIDTH_BASE);
			
			c_print("Downloading base image for car: " . carToString($car));
			c_print("URL: \"$url\"");
			$nonVerboseOutputLastIteration = true;
			
			try
			{
				downloadImage($tempBaseFilename, $url);
			}
			catch (Exception $e)
			{
				c_print("ERROR: Download image failed for URL \"$url\" to file \"$tempBaseFilename\".");
				c_print($e->getMessage());
				
				if (file_exists($tempBaseFilename))
				{
					if (!unlink($tempBaseFilename))
						c_print("WARNING: Unable to delete temp base image file after failed download: \"$tempBaseFilename\".");
				}
				
				$retryBaseImageDownloadsForCars[] = $car;
				continue;
			}
			
			// move temp image
			if (!rename($tempBaseFilename, $baseFilename))
			{
				c_print("ERROR: Unable to rename temp base image file \"$tempBaseFilename\" to \"$baseFilename\".");
				
				if (file_exists($tempBaseFilename))
				{
					if (!unlink($tempBaseFilename))
						c_print("WARNING: Unable to delete temp base image file after failed rename: \"$tempBaseFilename\"");
				}
				
				continue;
			}
			
			++$numBaseImageDownloadsSucceeded;
		}
	}
	
	$result = new stdClass();
	$result->retryBaseImageDownloadsForCars = $retryBaseImageDownloadsForCars;
	$result->numBaseImageDownloadsTried     = $numBaseImageDownloadsTried;
	$result->numBaseImageDownloadsSucceeded = $numBaseImageDownloadsSucceeded;
	$result->numBaseImageDownloadsFailed    = $numBaseImageDownloadsTried - $numBaseImageDownloadsSucceeded;
	$result->numBaseImageDownloadsSkipped   = count($cars) - $numBaseImageDownloadsTried;
	
	return $result;
}



/*****************************************************************************************************
 * Generate Car Image Type
 *
 * Generates an image with the given width from a processed base image.
 */

function generateCarImageType($fromFilename, $tempFilename, $newFilename, $isDetails)
{
	$type = $isDetails? 'detail' : 'icon';
	
	// generate the image to the temp lcoation
	$result = ImageManager::generateCarImage($fromFilename, $tempFilename, $isDetails? CAR_IMAGE_WIDTH_DETAIL : CAR_IMAGE_WIDTH_ICON);
	
	if ($result->status !== 0 || count($result->output) > 0)
	{
		c_print("ERROR: Failed to generate $type image:");
		c_print("ERROR: external program returned non-zero status ({$result->status}) or had output:");
		printExternalResult($result);
		
		if (file_exists($tempFilename))
		{
			if (!unlink($tempFilename))
				c_print("WARNING: Unable to delete temp $type image file after failed image generation: \"$tempFilename\"");
		}
		
		return false;
	}
	
	// move the image from the temp location to the final location
	if (!rename($tempFilename, $newFilename))
	{
		c_print("ERROR: Unable to rename temp $type image file \"$tempFilename\" to \"$newFilename\".");
		
		if (file_exists($tempFilename))
		{
			if (!unlink($tempFilename))
				c_print("WARNING: Unable to delete temp $type image file after failed rename: \"$tempFilename\"");
		}
		
		return false;
	}
	
	return true;
}




/*****************************************************************************************************
 * Generate Car Images
 *
 * Takes an array of cars and generates icon and detail images for each.
 */

function generateCarImages($cars, $regenerateImages)
{
	$numCarsTriedGeneratingImages     = 0;
	$numCarsSucceededGeneratingImages = 0;
	$numBaseImageProcessingsTried     = 0;
	$numBaseImageProcessingsSuceeded  = 0;
	$numImageGenerationsTried         = 0;
	$numImageGenerationsSucceeded     = 0;
	
	$nonVerboseOutputLastIteration = false;
	
	foreach ($cars as $car)
	{
		if ($nonVerboseOutputLastIteration)
			c_print('');
		
		$nonVerboseOutputLastIteration = false;
		
		$car->generatedIconImage = false;
		$car->generatedDetailImage = false;
		
		if (!isset($car->imageName) || $car->imageName === NULL || strlen($car->imageName) === 0)
		{
			c_print('WARNING: No imageName for car, skipping: ' . carToString($car));
			$nonVerboseOutputLastIteration = true;
			
			continue;
		}
		
		
		$tempProcessedBaseFilename = ImageManager::getImageFilename($car->imageName, CAR_IMAGE_TYPE_PROCESSED_BASE, false, true);
		$tempIconFilename          = ImageManager::getImageFilename($car->imageName, CAR_IMAGE_TYPE_ICON,           false, true);
		$tempDetailFilename        = ImageManager::getImageFilename($car->imageName, CAR_IMAGE_TYPE_DETAIL,         false, true);
		
		$baseFilename   = ImageManager::getImageFilename($car->imageName, CAR_IMAGE_TYPE_BASE);
		$iconFilename   = ImageManager::getImageFilename($car->imageName, CAR_IMAGE_TYPE_ICON);
		$detailFilename = ImageManager::getImageFilename($car->imageName, CAR_IMAGE_TYPE_DETAIL);
		
		
		// don't process if we are not regenerating images and both images already exist
		if (!$regenerateImages && file_exists($iconFilename) && file_exists($detailFilename))
			continue;
		
		// make sure the base image exists
		if (!file_exists($baseFilename))
		{
			c_print('WARNING: Unable to generate images for car, skipping: ' . carToString($car));
			C_print("Base image file does not exist: \"$baseFilename\"");
			$nonVerboseOutputLastIteration = true;
			
			continue;
		}
		
		++$numCarsTriedGeneratingImages;
		
		c_print('Generating images for car: ' . carToString($car));
		$nonVerboseOutputLastIteration = true;
		
		
		// process base image with HWIP
		++$numBaseImageProcessingsTried;
		
		if (file_exists($tempProcessedBaseFilename))
		{
			if (!unlink($tempProcessedBaseFilename))
				c_print("WARNING: Unable to delete existing base processed image file before processing: \"$tempBaseProcessedFilename\"");
		}
		
		$result = ImageManager::processCarBaseImage($baseFilename, $tempProcessedBaseFilename);
		
		if ($result->status !== 0 || count($result->output) > 0)
		{
			c_print("ERROR: Failed to process base image for car: " . carToString($car));
			c_print("ERROR: external program returned non-zero status ({$result->status}) or had output:");
			printExternalResult($result);
			$nonVerboseOutputLastIteration = true;
			
			if (file_exists($tempProcessedBaseFilename))
			{
				if (!unlink($tempProcessedBaseFilename))
					c_print("WARNING: Unable to delete temp base processed image file after failed processing: \"$tempProcessedBaseFilename\"");
			}
		
			continue;
		}
		
		++$numBaseImageProcessingsSuceeded;
		
		
		
		// generate images
		// icon
		$failedIconImageGeneration = false;
		++$numImageGenerationsTried;
		
		if (generateCarImageType($tempProcessedBaseFilename, $tempIconFilename, $iconFilename, CAR_IMAGE_WIDTH_ICON, false))
		{
			$car->generatedIconImage = true;
			++$numImageGenerationsSucceeded;
		}
		else
			$failedIconImageGeneration = true;
		
		// detail
		$failedDetailImageGeneration = false;
		++$numImageGenerationsTried;
		
		if (generateCarImageType($tempProcessedBaseFilename, $tempDetailFilename, $detailFilename, CAR_IMAGE_WIDTH_DETAIL, true))
		{
			$car->generatedDetailImage = true;
			++$numImageGenerationsSucceeded;
		}
		else
			$failedDetailImageGeneration = true;
		
		// remove base processed image
		if (file_exists($tempProcessedBaseFilename))
		{
			if (!unlink($tempProcessedBaseFilename))
				c_print("WARNING: Unable to delete temp base processed image file after generating images: \"$tempProcessedBaseFilename\"");
		}
		
		if (!$failedIconImageGeneration && !$failedDetailImageGeneration)
			++$numCarsSucceededGeneratingImages;
	}
	
	$result = new stdClass();
	$result->numCarsTriedGeneratingImages     = $numCarsTriedGeneratingImages;
	$result->numCarsSucceededGeneratingImages = $numCarsSucceededGeneratingImages;
	$result->numCarsFailedGeneratingImages    = $numCarsTriedGeneratingImages - $numCarsSucceededGeneratingImages;
	$result->numCarsSkippedGeneratingImages   = count($cars) - $numCarsTriedGeneratingImages;
	
	$result->numBaseImageProcessingsTried     = $numBaseImageProcessingsTried;
	$result->numBaseImageProcessingsSuceeded  = $numBaseImageProcessingsSuceeded;
	$result->numBaseImageProcessingsFailed    = $numBaseImageProcessingsTried - $numBaseImageProcessingsSuceeded;
	
	$result->numImageGenerationsTried         = $numImageGenerationsTried;
	$result->numImageGenerationsSucceeded     = $numImageGenerationsSucceeded;
	$result->numImageGenerationsFailed        = $numImageGenerationsTried - $numImageGenerationsSucceeded;
	
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
$skipS3Sync = false;

function getFlagValue($argv, &$i, $label)
{
	$errorString = "ERROR: {$argv[$i]} flag used without $label following it.\nUse -help flag for usage help.\n";
	
	++$i;
	
	if ($i >= count($argv))
		die($errorString);
	
	$flagValue = $argv[$i];
	
	if (strlen($flagValue) === 0 || $flagValue[0] === '-')
		die($errorString);
	
	return $flagValue;
}

for ($i = 1; $i < count($argv); ++$i)
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
		
		case '-d':
		case '-redownloadBaseImages':
			$redownloadBaseImages = true;
			break;
		
		case '-s':
		case '-search':
			$searchTerm = getFlagValue($argv, $i, 'a search term');
			break;
		
		case '-l':
		case '-log':
			++$i;
			$logFilename = getFlagValue($argv, $i, 'a filename');
			break;
		
		case '-y':
		case '-skipS3Sync':
			$skipS3Sync = true;
			break;
		
		case '-q':
		case '-quiet':
			$quietMode = true;
			break;
		
		default:
			die("ERROR: unknown argument used \"{$argv[$i]}\".\nUse -help flag for usage help.\n");
	}
}

if ($skipImages && $regenerateImages)
	die("ERROR: Cannot use -skipImages and -regenerateImages flags together. This does not make sense.\nUse -help flag for usage help.\n");

if ($skipImages && $redownloadBaseImages)
	die("ERROR: Cannot use -skipImages and -redownloadBaseImages flags together. This does not make sense.\nUse -help flag for usage help.\n");

if ($skipImages && $skipS3Sync)
	die("ERROR: Cannot use -skipImages and -skipS3Sync flags together. This does not make sense.\nUse -help flag for usage help.\n");


ini_set("error_log", $logFilename);

if ($quietMode)
	ob_start();

c_print('');
c_print('');
c_print('');
c_print('');
c_print('');
c_print('');
c_print('******************************************************************************************');
c_print('******************************************************************************************');
c_print('* Mining Start');
c_print('* ' . date('D, M d Y H:i:s O') . ' (' . time() . ')');
c_print('******************************************************************************************');
c_print('******************************************************************************************');
c_print('');

if ($skipImages)
  c_print('* Skipping images');

if ($regenerateImages)
  c_print('* Regenerating icon and detail images');

if ($redownloadBaseImages)
  c_print('* Redownloading base images');

if ($skipS3Sync)
	c_print('* Skipping S3 sync');



c_print('');
c_print('Searching' . ($searchTerm === ' ' ? '' : ' "' . $searchTerm . '"') . '...');

$startTime = time();
$detailURLs = HotWheelsAPI::search($searchTerm, 300);
$endTime = time();



if ($detailURLs === NULL)
{
	c_print('ERROR: getDetailURLS returned null');
	$shouldOuput = true;
	die();
}

$numDetailURLs = count($detailURLs);
c_print('Done. Found ' . $numDetailURLs . ' detail URLs');
c_print('Took ' . formatDuration($endTime - $startTime));

$db = new DB();

c_print('');
c_print('*******************************************************************');
c_print('* Mining Car Details');
c_print('*******************************************************************');
c_print('');

$cars = array();

$startTime = time();
$result = getCars($detailURLs, $db, $cars);
$endTime = time();

c_print('');
c_print('*********************************************');
c_print('');

c_print('Took ' . formatDuration($endTime - $startTime));
printTable(array(
	array($result->numDetailURLsTried     , ' detail URLs tried'),
	array($result->numDetailURLsSucceeded , ' detail URLs succeeded'),
	array($result->numDetailURLsFailed    , ' detail URLs failed'),
	array($result->numDetailURLsSkipped   , ' detail URLs skipped')
));
c_print('');
printTable(array(
	array($result->numCarsFound     , ' cars found'),
	array($result->numCarsSucceeded , ' cars succeeded'),
	array($result->numCarsFailed    , ' cars failed')
));
c_print('');
printTable(array(
	array($result->numCarsAdded     , ' cars added'),
	array($result->numCarsUpdated   , ' cars updated'),
	array($result->numCarsNoChange  , ' cars did not change')
));

if ($result->numDetailURLsTried === 0)
{
	c_print('');
	c_print('WARNING: No detail URLs were tried. Search may have returned no results or all detail URLs returned may have been empty.');
	$shouldOutput = true;
}

if ($result->numDetailURLsSkipped > 0 || $result->numCarsFailed > 0)
	$shouldOutput = true;

if ($result->numDetailURLsFailed > 0)
{
	c_print('');
	
	if ($result->numDetailURLsFailed > $result->numDetailURLsTried * 0.25 && $result->numDetailURLsFailed > 20)
	{
		c_print($result->numDetailURLsFailed . ' detail URLs failed. This is more than 1/4th of the detail URLs tried (' . $result->numDetailURLsTried . ') and more than 20 and will not rety.');
		$shouldOutput = true;
	}
	else
	{
		c_print($result->numDetailURLsFailed . ' detail URLs failed. Retrying those in 10 seconds...');
		sleep(10);
		
		c_print('');
		c_print('*********************************************');
		c_print('');
		
		$startTime = time();
		$result = getCars($result->detailURLsFailed, $db, $cars);
		$endTime = time();
		
		c_print('');
		c_print('*********************************************');
		c_print('');
		
		c_print('Took ' . formatDuration($endTime - $startTime));
		printTable(array(
			array($result->numDetailURLsTried     , ' detail URLs tried'),
			array($result->numDetailURLsSucceeded , ' detail URLs succeeded'),
			array($result->numDetailURLsFailed    , ' detail URLs failed'),
			array($result->numDetailURLsSkipped   , ' detail URLs skipped')
		));
		c_print('');
		printTable(array(
			array($result->numCarsFound     , ' cars found'),
			array($result->numCarsSucceeded , ' cars succeeded'),
			array($result->numCarsFailed    , ' cars failed')
		));
		c_print('');
		printTable(array(
			array($result->numCarsAdded    , ' cars added'),
			array($result->numCarsUpdated  , ' cars updated'),
			array($result->numCarsNoChange , ' cars did not change')
		));
		
		if ($result->numDetailURLsFailed > 0 ||
			$result->numDetailURLsSkipped > 0 ||
			$result->numCarsFailed > 0)
		{
			$shouldOutput = true;
		}
	}
}

if (!$skipImages)
{
	c_print('');
	c_print('*******************************************************************');
	c_print('* Mining Images');
	c_print('*******************************************************************');
	c_print('');
	
	$startTime = time();
	$result = downloadCarBaseImages($cars, $redownloadBaseImages);
	$endTime = time();
	
	c_print('');
	c_print('*********************************************');
	c_print('');
	
	$numRetryBaseImageDownloadsForCars = count($result->retryBaseImageDownloadsForCars);
	
	c_print('Took ' . formatDuration($endTime - $startTime));
	$table = array(
		array($result->numBaseImageDownloadsTried     , ' cars tried downloading base image'),
		array($result->numBaseImageDownloadsSucceeded , ' cars successfully downloaded base images'),
		array($result->numBaseImageDownloadsFailed    , ' cars failed downloading base image'),
		array($result->numBaseImageDownloadsSkipped   , ' cars skipped downloading base image'),
	);
	if (count($result->numBaseImageDownloadsFailed) !== 0 || $numRetryBaseImageDownloadsForCars !== 0)
		$table[] = array($numRetryBaseImageDownloadsForCars, ' cars can be retried');
	
	printTable($table);
	
	if ($numRetryBaseImageDownloadsForCars - $result->numBaseImageDownloadsFailed > 0)
		$shouldOutput = true;
	
	if ($result->numBaseImageDownloadsFailed > 0)
	{
		c_print('');
	
		if ($numRetryBaseImageDownloadsForCars > $result->numBaseImageDownloadsTried * 0.25 && $numRetryBaseImageDownloadsForCars > 20)
		{
			c_print($numRetryBaseImageDownloadsForCars . ' cars failed downloading base image and can be retried. This is more than 1/4th of the cars that tried downloading base image (' . $result->numBaseImageDownloadsTried . ') and more than 20 and will not rety.');
			$showOutput = true;
		}
		else
		{
			c_print($numRetryBaseImageDownloadsForCars . ' cars faield downloading base image and can be retried. Retrying those in 10 seconds...');
			sleep(10);
			
			c_print('');
			c_print('*********************************************');
			c_print('');
		
			$startTime = time();
			$result = downloadCarBaseImages($result->retryBaseImageDownloadsForCars, $redownloadBaseImages);
			$endTime = time();
		
			c_print('');
			c_print('*********************************************');
			c_print('');
			
			c_print('Took ' . formatDuration($endTime - $startTime));
			printTable(array(
				array($result->numBaseImageDownloadsTried     , ' cars tried downloading base image'),
				array($result->numBaseImageDownloadsSucceeded , ' cars successfully downloaded base images'),
				array($result->numBaseImageDownloadsFailed    , ' cars failed downloading base image'),
				array($result->numBaseImageDownloadsSkipped   , ' cars skipped downloading base image')
			));
			
			if ($result->numBaseImageDownloadsFailed > 0)
				$shouldOutput = true;
		}
	}
	
	c_print('');
	c_print('*******************************************************************');
	c_print('* Generating Images');
	c_print('*******************************************************************');
	c_print('');
	
	$startTime = time();
	$result = generateCarImages($cars, $regenerateImages);
	$endTime = time();
	
	c_print('');
	c_print('*********************************************');
	c_print('');
	
	c_print('Took ' . formatDuration($endTime - $startTime));
	printTable(array(
		array($result->numCarsTriedGeneratingImages     , ' cars tried generating images'),
		array($result->numCarsSucceededGeneratingImages , ' cars successfully generated all images'),
		array($result->numCarsFailedGeneratingImages    , ' cars failed generating one or more images'),
		array($result->numCarsSkippedGeneratingImages   , ' cars skipped generating images'),
	));
	c_print('');
	printTable(array(
		array($result->numBaseImageProcessingsTried    , ' base image processings tried'),
		array($result->numBaseImageProcessingsSuceeded , ' base image processings succeeded'),
		array($result->numBaseImageProcessingsFailed   , ' base image processings failed'),
	));
	c_print('');
	printTable(array(
		array($result->numImageGenerationsTried     , ' image generations tried'),
		array($result->numImageGenerationsSucceeded , ' image generations succeeded'),
		array($result->numImageGenerationsFailed    , ' image generations failed')
	));
	
	if ($result->numCarsFailedGeneratingImages > 0 ||
		$result->numBaseImageProcessingsFailed > 0 ||
		$result->numImageGenerationsFailed > 0)
	{
		$shouldOuput = true;
	}
	
	
	if (!$skipS3Sync)
	{
		c_print('');
		c_print('*******************************************************************');
		c_print('* Syncing Images with S3');
		c_print('*******************************************************************');
		c_print('');
		
		$startTime = time();
		$result = ImageManager::syncImagesWithS3(ImageManager::getImagePath(CAR_IMAGE_TYPE_ICON), S3_CAR_IMAGE_BUCKET, S3_CAR_IMAGE_KEY_BASE_PATH_ICON);
		$endTime = time();
		
		if ($result->status !== 0)
		{
			c_print("ERROR: Failed to sync icon images with S3:");
			c_print("ERROR: external program returned non-zero status ({$result->status}):");
			$shouldOuput = true;
		}
		
		printExternalResult($result);
		c_print('');
		
		$startTime2 = time();
		$result = ImageManager::syncImagesWithS3(ImageManager::getImagePath(CAR_IMAGE_TYPE_DETAIL), S3_CAR_IMAGE_BUCKET, S3_CAR_IMAGE_KEY_BASE_PATH_DETAIL);
		$endTime2 = time();
		
		if ($result->status !== 0)
		{
			c_print("ERROR: Failed to sync detail images with S3:");
			c_print("ERROR: external program returned non-zero status ({$result->status}):");
			$shouldOuput = true;
		}
		
		printExternalResult($result);
		
		c_print('');
		c_print('Took ' . formatDuration(($endTime - $startTime) + ($endTime2 - $startTime2)));
		c_print('');
		c_print('*********************************************');
	}
}

$db->close();

c_print('');
c_print('*******************************************************************');
c_print('Mining Complete');
c_print('*******************************************************************');

if ($quietMode)
{
	$output = ob_get_clean();
	
	if (strlen($output) > $explicitOutputLength)
		$shouldOutput = true;
	
	if ($shouldOutput)
		echo $output;
	else
	{
		if (count($carsAdded) > 0)
		{
			echo count($carsAdded), " cars added:\n";
			
			foreach ($carsAdded as $car)
				echo carToString($car), "\n"; 
		}
		
		if (count($carsUpdated) > 0)
		{
			if (count($carsAdded) > 0)
				echo "\n\n\n";
			
			echo count($carsUpdated), " cars updated:\n";
			
			foreach ($carsUpdated as $carUpdated)
			{
				echo carToString($carUpdated->car), "\n";
				
				foreach ($carUpdated->updatedFields as $fieldName => $update)
				{
					c_print($fieldName);
					c_print('  from: ' . $update['from']);
					c_print('  to  : ' . $update['to']);
				}
			}
		}
	}
}

?>
