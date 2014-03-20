<?php
/*
php mine.php searchTerm(optinal) flags

searchTerm - (Optional) will use the given term to search the Hot Wheels site instead of " " which returns all cars

flags:
updateExistingImages - will re-generate all icon and detail images even if they already exist.
redownloadBaseImages - will download the base images used to generate the icon and detail images even if it already exists

php mine.php corvette updateExistingImages
*/

require 'www/includes/hotWheelsAPI.php';
require 'config.php';
require 'www/includes/database.php';

ini_set("error_log", MINE_LOG_FILE);

function c_log($str)
{
	error_log($str);
	echo $str, "\n";
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

function getCarDetails($detailURLs, $db, &$carDetailsList)
{
	$detailURLsFailed = array();
	
	$carDetailNum = 0;
	foreach ($detailURLs as $detailURL)
	{
		++$carDetailNum;
		
		echo "Trying detail URL ($carDetailNum): $detailURL\n";
		
		if (strlen($detailURL) === 0)
		{
			c_log('empty car detail URL');
			continue;
		}
		
		// get details
		$carDetails = HotWheelsAPI::getCarDetails($detailURL);
		
		if (is_string($carDetails))
		{
			c_log('getCarDetails failed for "' . $detailURL . '": ' . $carDetails);
			
			$detailURLsFailed[] = $detailURL;
			continue;
		}
		
		$carDetails->id        = strtolower($carDetails->id);
		$carDetails->name      = clean($carDetails->name);
		$carDetails->toyNumber = strtoupper(clean($carDetails->toyNumber));
		$carDetails->segment   = clean($carDetails->segment);
		$carDetails->series    = clean($carDetails->series);
		$carDetails->make      = clean($carDetails->make);
		$carDetails->color     = clean($carDetails->color);
		$carDetails->style     = clean($carDetails->style);
		
		echo "Found car \"{$carDetails->id}\" - \"{$carDetails->toyNumber}\" - \"{$carDetails->name}\"\n";
		
		// create image name
		$imageName = preg_replace('/[^a-zA-Z0-9]/', '_', $carDetails->id);
		$carDetails->imageName = $imageName;
		
		// create sortname
		$sortName = strtolower($carDetails->name);
		$sortName = preg_replace('/[^a-z0-9 ]/', '', $sortName);
		
		if (preg_match('/^[0-9]+s/', $sortName))
		{
			$index = strpos($sortName, 's');
			$sortName = substr($sortName, 0, $index) . substr($sortName, $index + 1);
		}
		
		if (strpos($sortName, 'the ') === 0)
			$sortName = substr($sortName, 4);
		
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
		
		$carDetails->sortName = $sortName;
		
		// insert or update db
		try
		{
			$db->insertOrUpdateCar(
					$carDetails->id,
					$carDetails->name,
					$carDetails->toyNumber,
					$carDetails->segment,
					$carDetails->series,
					$carDetails->make,
					$carDetails->color,
					$carDetails->style,
					$carDetails->numUsersCollected,
					$carDetails->imageName,
					$carDetails->sortName);
			
			$carDetailsList[] = $carDetails;
		}
		catch (Exception $e)
		{
			c_log('insertOrUpdateCar failed for "' . $carDetails->id . '": ' . $e->getMessage());
			
			$detailURLsFailed[] = $detailURL;
			continue;
		}
	}
	
	return $detailURLsFailed;
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

function runExternal($cmd, $logName)
{
	$cmd .= ' 2>&1';
	$output = array();
	$status = -1;

	exec($cmd, $output, $status);
	
	if ($status !== 0 || count($output) > 0)
	{
		c_log('ERROR: ' . $logName . ' returned non-zero status (' . $status . ') or had output');
		c_log('----------');
		c_log($cmd);
		
		foreach ($output as $line)
			c_log($line);
		
		c_log('----------');
		return false;
	}
	
	return true;
}

function updateCarImages($carDetailsList, $updateExistingImages, $redownloadBaseImages)
{
	$numCarsUpdating = 0;
	$numImagesDownloaded = 0;
	$numImageDownloadsFailed = 0;
	$numUpdatedImages = 0;
	$numUpdateImagesFailed = 0;
	
	foreach ($carDetailsList as $carDetails)
	{
		$iconFilename   = HOTWHEELS2_IMAGE_PATH . $carDetails->imageName . HOTWHEELS2_IMAGE_ICON_SUFFIX   . HOTWHEELS2_IMAGE_EXT;
		$detailFilename = HOTWHEELS2_IMAGE_PATH . $carDetails->imageName . HOTWHEELS2_IMAGE_DETAIL_SUFFIX . HOTWHEELS2_IMAGE_EXT;
		
		// check if they exist already
		if ($updateExistingImages || !file_exists($iconFilename) || !file_exists($detailFilename))
		{
			++$numCarsUpdating;
			echo  "Updating images for \"{$carDetails->id}\" - \"{$carDetails->toyNumber}\" - \"{$carDetails->name}\"\n";
			
			$baseFilename = HOTWHEELS2_IMAGE_PATH . $carDetails->imageName . HOTWHEELS2_IMAGE_BASE_SUFFIX . HOTWHEELS2_IMAGE_EXT;
			
			// check if base image already exists
			if ($redownloadBaseImages || !file_exists($baseFilename))
			{
				echo  'Downloading base image...';
				
				if (file_exists($baseFilename))
				{
					if (!unlink($baseFilename))
					{
						echo("\n");
						c_log('WARNING: unable to delete existing base image file "' . $baseFilename . '" before re-download!');
					}
				}
				
				$url = $carDetails->getImageURL(MINE_CAR_IMAGE_BASE_WIDTH);
				try
				{
					downloadImage($baseFilename, $url);
				}
				catch (Exception $e)
				{
					++$numImageDownloadsFailed;
					
					echo("\n");
					c_log('ERROR: download image failed for URL "' . $url . '": ' . $e->getMessage());
					
					if (file_exists($baseFilename))
					{
						if (!unlink($baseFilename))
							c_log('WARNING: unable to delete base image file "' . $baseFilename . '" before failed download!');
					}
					
					continue;
				}
				
				++$numImagesDownloaded;
				echo(" done\n");
				
				// trim background with hwip
				if (!runExternal('hwip/hwip "' . $baseFilename . '" "' . $baseFilename . '" ' . MINE_HWIP_ALPHA_THRESHOLD . ' ' . MINE_HWIP_PADDING, 'hwip'))
				{
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
			
			if (!runExternal('convert "' . $baseFilename . '" -resize ' . MINE_CAR_IMAGE_ICON_WIDTH . ' "' . $iconFilename . '"', 'convert'))
			{
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
			
			if (!runExternal('convert "' . $baseFilename . '" -resize ' . MINE_CAR_IMAGE_DETAIL_WIDTH . ' "' . $detailFilename . '"', 'convert'))
			{
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
	$result->numCarsUpdating         = $numCarsUpdating;
	$result->numImagesDownloaded     = $numImagesDownloaded;
	$result->numImageDownloadsFailed = $numImageDownloadsFailed;
	$result->numUpdatedImages        = $numUpdatedImages;
	$result->numUpdateImagesFailed   = $numUpdateImagesFailed;
	
	return $result;
}

$updateExistingImages = false;
$redownloadBaseImages = false;
$searchTerm = ' ';

foreach ($argv as $arg)
{
	if ($arg === 'updateExistingImages')
		$updateExistingImages = true;
	
	if ($arg === 'redownloadBaseImages')
		$redownloadBaseImages = true;
}

if (isset($argv[1]) && $argv[1] !== 'updateExistingImages' && $argv[1] !== 'redownloadBaseImages')
	$searchTerm = $argv[1];

c_log('******************************************************************************************');
c_log('******************************************************************************************');
c_log('* Mining Start');
c_log('******************************************************************************************');
c_log('******************************************************************************************');
c_log('');

if ($updateExistingImages)
  c_log('* Updating existing images');

if ($redownloadBaseImages)
  c_log('* Re-downloading base images');

c_log('');
c_log('Searching' . ($searchTerm === ' ' ? '' : ' "' . $searchTerm . '"') . '...');
$detailURLs = HotWheelsAPI::search($searchTerm, 300);

if (is_string($detailURLs))
{
	c_log('ERROR: Search failed: ' . $detailURLs);
	die();
}

$numDetailURLs = count($detailURLs);
c_log('Done. Found ' . $numDetailURLs . ' detail URLs');

$db = new DB();

c_log('');
c_log('*********************************************');
c_log('* Mining Car Details');
c_log('*********************************************');
c_log('');

$carDetailsList = array();
$detailURLsFailed = getCarDetails($detailURLs, $db, $carDetailsList);

c_log('');
c_log('*********************************************');
c_log('');

$numDetailURLsFailed = count($detailURLsFailed);
$numCarDetails = count($carDetailsList);
$numDetailURLsTried = $numCarDetails + $numDetailURLsFailed;

c_log($numDetailURLsTried . ' detail URLs tried');
c_log($numDetailURLsFailed . ' detail URLs failed');
c_log(($numDetailURLs - $numDetailURLsTried) . ' detail URLs skipped');
c_log($numCarDetails . ' cars found');

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
		$detailURLsFailed = getCarDetails($detailURLsFailed, $db, $carDetailsList);
		
		c_log('');
		c_log('*********************************************');
		c_log('');
		
		$numDetailURLs = $numDetailURLsFailed;
		$numDetailURLsFailed = count($detailURLsFailed);
		$numCarDetails = count($carDetailsList) - $numCarDetails;
		$numDetailURLsTried = $numCarDetails + $numDetailURLsFailed;
		
		c_log($numDetailURLsTried . ' detail URLs tried');
		c_log($numDetailURLsFailed . ' detail URLs failed');
		c_log(($numDetailURLs - $numDetailURLsTried) . ' detail URLs skipped');
		c_log('Found ' . $numCarDetails . ' cars');
	}
}

c_log('');
c_log('*********************************************');
c_log('* Mining Images');
c_log('*********************************************');
c_log('');

$result = updateCarImages($carDetailsList, $updateExistingImages, $redownloadBaseImages);

c_log('');
c_log('*********************************************');
c_log('');

c_log($result->numCarsUpdating . ' cars updated');
c_log((count($carDetailsList) - $result->numCarsUpdating) . ' cars skipped');
c_log($result->numImagesDownloaded     . ' images downloaded');
c_log($result->numImageDownloadsFailed . ' image downloads failed');
c_log($result->numUpdatedImages        . ' images updated');
c_log($result->numUpdateImagesFailed   . ' image updates failed');

$db->close();

c_log('');
c_log('Mining Complete');
?>
