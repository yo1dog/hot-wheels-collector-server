<?php
/*
php mine.php flag

flags:
noImages     - will not download any images
updateImages - will download images for all cars even if they already exist

php mine.php noImages
*/

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
		
		c_log('Downloaded ' . $imgType . ' image for "' . $id . '"');
	}
	catch(Exception $e)
	{
		if ($fp)
			fclose($fp);
		if ($ch)
			curl_close($ch);
		
		c_log('Download ' . $imgType . ' image failed for "' . $id . '": "' . $url . '": ' . $e->getMessage());
		
		if (file_exists($filename))
		{
			if (!unlink($filename))
				c_log('WARNING: unable to delete image file "' . $filename . '" after failed download!');
		}
		
		return false;
	}
	
	return true;
}

function runExternal($cmd, $logName)
{
	$cmd .= ' 2>&1';
	$output = array();
	$status = -1;

	exec($cmd, $output, $status);
	
	if ($status !== 0 || count($output) > 0)
	{
		c_log($logName . ' returned non-zero status (' . $status . ') or had output');
		c_log('**********');
		c_log($cmd);
		
		foreach ($output as $line)
			c_log($line);
		
		c_log('**********');
		return false;
	}
	
	return true;
}




function mineCars($carDetailURLs, $db, $downloadImages, $updateImages)
{
	$failedCarDetailURLs = array();
	
	$numMined = 0;
	foreach ($carDetailURLs as $carDetailURL)
	{
		if (strlen($carDetailURL) === 0)
		{
			c_log('empty car detail URL');
			continue;
		}
		
		// get details
		$carDetails = HotWheelsAPI::getCarDetails($carDetailURL);
		
		if (is_string($carDetails))
		{
			c_log('getCarDetails failed for "' . $carDetailURL . '": ' . $carDetails);
			
			$failedCarDetailURLs[] = $carDetailURL;
			continue;
		}
		
		// create image name
		$imageName = preg_replace('/[^a-zA-Z0-9]/', '_', $carDetails->id);
		
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
		
		
		$name = clean($carDetails->name);
		
		// if the name starts with 80's change it to '80
		/*
		$ascii0 = ord($name[0]);
		$ascii1 = ord($name[1]);
		if ($ascii0 > 47 && $ascii0 < 58 &&
			$ascii1 > 47 && $ascii1 < 58)
		{
			if ($name[2] === '\'' && $name[3] !== 's')
				$name = '\'' . substr($name, 0, 2) . substr($str, 3);
		}
		*/
		
		// insert or update db
		try
		{
			$db->insertOrUpdateCar(
					$carDetails->id,
					$name,
					strtoupper(clean($carDetails->toyNumber)),
					clean($carDetails->segment),
					clean($carDetails->series),
					clean($carDetails->make),
					clean($carDetails->color),
					clean($carDetails->style),
					$carDetails->numUsersCollected,
					$imageName,
					$sortName);
		}
		catch (Exception $e)
		{
			c_log('insertOrUpdateCar failed for "' . $carDetails->id . '": ' . $e->getMessage());
			
			$failedCarDetailURLs[] = $carDetailURL;
			continue;
		}
		
		++$numMined;
		echo 'Mined (', $numMined, ') "', $carDetails->id, '" - "', $name, "\"\n";
		
		// download images
		if ($downloadImages)
		{
			$iconFilename   = HOTWHEELS2_IMAGE_PATH . $imageName . HOTWHEELS2_IMAGE_ICON_SUFFIX   . HOTWHEELS2_IMAGE_EXT;
			$detailFilename = HOTWHEELS2_IMAGE_PATH . $imageName . HOTWHEELS2_IMAGE_DETAIL_SUFFIX . HOTWHEELS2_IMAGE_EXT;
			
			// check if they exist already
			if ($updateImages || !file_exists($iconFilename) || !file_exists($detailFilename))
			{
				if (file_exists($iconFilename))
				{
					if (!unlink($iconFilename))
						c_log('WARNING: unable to delete existing icon image file "' . $iconFilename . '" before update!');
				}
				
				if (file_exists($detailFilename))
				{
					if (!unlink($detailFilename))
						c_log('WARNING: unable to delete existing detail image file "' . $detailFilename . '" before update!');
				}
				
					
				$baseFilename = HOTWHEELS2_IMAGE_PATH . $imageName . HOTWHEELS2_IMAGE_BASE_SUFFIX . HOTWHEELS2_IMAGE_EXT;
				
				if (!downloadImage($baseFilename, $carDetails->getImageURL(MINE_CAR_IMAGE_BASE_WIDTH), $carDetails->id, 'base'))
					continue;
				
				// trim background with hwip
				if (!runExternal('hwip/hwip "' . $baseFilename . '" "' . $baseFilename . '" ' . MINE_HWIP_ALPHA_THRESHOLD . ' ' . MINE_HWIP_PADDING, 'hwip'))
				{
					if (!unlink($baseFilename))
						c_log('WARNING: unable to delete base image file "' . $baseFilename . '" after failed hwip!');
					
					continue;
				}
				
				// resize images
				if (!runExternal('convert "' . $baseFilename . '" -resize ' . MINE_CAR_IMAGE_ICON_WIDTH . ' "' . $iconFilename . '"', 'convert'))
				{
					if (file_exists($iconFilename))
					{
						if (!unlink($iconFilename))
							c_log('WARNING: unable to delete icon image file "' . $iconFilename . '" after failed convert!');
					}
					
					if (!unlink($baseFilename))
						c_log('WARNING: unable to delete base image file "' . $baseFilename . '" after failed convert!');
					
					continue;
				}
				
				if (!runExternal('convert "' . $baseFilename . '" -resize ' . MINE_CAR_IMAGE_DETAIL_WIDTH . ' "' . $detailFilename . '"', 'convert'))
				{
					if (file_exists($detailFilename))
					{
						if (!unlink($detailFilename))
							c_log('WARNING: unable to delete detail image file "' . $detailFilename . '" after failed convert!');
					}
					
					if (!unlink($baseFilename))
						c_log('WARNING: unable to delete base image file "' . $baseFilename . '" after failed convert!');
					
					continue;
				}
				
				if (!unlink($baseFilename))
					c_log('WARNING: unable to delete base image file "' . $baseFilename . '"');
			}
		}
	}
	
	return $failedCarDetailURLs;
}


/*
// check if this is a proccessor
if (isset($argv[2])) 
{
	c_log('Proccessing ' . $argv[2]);
	sleep(3);
	
	return;
}
*/



$downloadImages = true;
$updateImages   = false;
$searchTerm = ' ';

foreach ($argv as $arg)
{
	if ($arg === 'noimages')
		$downloadImages = false;
	
	if ($arg === 'updateimages')
		$updateImages = true;
}

if (!$downloadImages && $updateImages)
	c_log('WARNING: noimages and updateimages flags used. This does not make sense. Ignoring updateimages flag.');

if (isset($argv[1]) && $argv[1] !== 'noimages' && $argv[1] !== 'updateimages')
	$searchTerm = $argv[1];

c_log('Mining Start');

if (!$downloadImages)
  c_log('NOT Downloading images!');

if ($updateImages)
  c_log('UPDATING images!');


c_log('Searching...');
$carDetailURLs = HotWheelsAPI::search($searchTerm, 300);

if (is_string($carDetailURLs))
{
	c_log('Search failed: ' . $carDetailURLs);
	die();
}

$totalNumCars = count($carDetailURLs);

c_log('Done. Found ' . $totalNumCars . ' cars');

$db = new DB();

$failedCarDetailURLs = mineCars($carDetailURLs, $db, $downloadImages, $updateImages);

$numCarsFailed = count($failedCarDetailURLs);
if ($numCarsFailed > 0)
{
	if ($numCarsFailed > $totalNumCars / 4)
		c_log($numCarsFailed . ' car' . ($numCarsFailed > 1 ? 's' : '') . ' failed. This is more than 1/4th of the total cars (' . $totalNumCars . ') and will not rety.');
	else
	{
		c_log($numCarsFailed . ' car' . ($numCarsFailed > 1 ? 's' : '') . ' failed. Retrying those in 10 seconds...');
		sleep(10);
		
		$failedCarDetailURLs = mineCars($failedCarDetailURLs, $db, $downloadImages, $updateImages);
		
		$numCarsFailed = count($failedCarDetailURLs);
		if ($numCarsFailed > 0)
			c_log($numCarsFailed . ' car' . ($numCarsFailed > 1 ? 's' : '') . ' still failed after retry.');
	}
}

$db->close();
c_log('Mining Complete');

/*
// split the search results into sections
c_log('Splitting cars into ' . MINE_NUM_PROCESSORS . ' files');

$baseSectionLength = floor($totalNumCars / MINE_NUM_PROCESSORS);
$remainder = $totalNumCars % MINE_NUM_PROCESSORS;

$index = 0;
for ($pn = 0; $pn < MINE_NUM_PROCESSORS; ++$pn)
{
	$filename = MINE_CAR_LIST_FILENAME_PREFIX . $pn;
	$file = fopen($filename, 'w');
	
	if ($file === false)
		throw new Exception('Unable to open file for writing: ' . $filename);
	
	
	$length = $baseSectionLength;
	
	if ($remainder > 0)
	{
		++$length;
		--$remainder;
	}
	
	$endingIndex = $index + $length;
	
	for (; $index < $endingIndex; ++$index)
		fwrite($file, $carDetailURLs[$index] . "\n");
	
	fclose($file);
	c_log('Wrote ' . $length . ' cars to ' . $filename);
}

for ($pn = 0; $pn < MINE_NUM_PROCESSORS; ++$pn)
{
	c_log('Spawning processor ' . $pn);
	 exec('nice php mine.php ' . $pn);
}
*/
?>
