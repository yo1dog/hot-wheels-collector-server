<?php
// exception handler
class HTTPException extends Exception
{
	public $httpStatusCode;
	
	public function __construct($httpStatusCode, $message)
	{
		parent::__construct($httpStatusCode);
		
		$this->httpStatusCode = $httpStatusCode;
	}
}


set_exception_handler('exception_handler');

function exception_handler($exception)
{
	if (isset($exception->httpStatusCode))
		http_response_code($exception->httpStatusCode);
	else
		http_response_code(500);
	
	throw $exception;
}

function createCarImageName($id, $name)
{
	$imageName = preg_replace('/[^a-zA-Z0-9 ]/', '', $name);
	
	if (strlen($imageName) > HOTWHEELS2_IMAGE_NAME_TRUNCATE_LENGTH)
		$imageName = substr($imageName, 0, HOTWHEELS2_IMAGE_NAME_TRUNCATE_LENGTH);
	
	$imageName = str_replace(' ', '_', strtolower($imageName));
	
	return $id . '_' . $imageName;
}

function createCarSortName($carName)
{
	$sortName = strtolower($carName);
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
	
	return $sortName;
}

function proccessCarBaseImage($baseFilename, $newFilename)
{
	return runExternal(MINE_HWIP_LOCATION . ' ' .  escapeshellarg($baseFilename) . ' ' .  escapeshellarg($newFilename) . ' ' . MINE_HWIP_ALPHA_THRESHOLD . ' ' . MINE_HWIP_PADDING);
}

function generateCarImage($baseFilename, $newFilename, $width)
{
	return runExternal(MINE_CONVERT_LOCATION . ' ' .  escapeshellarg($baseFilename) . ' -resize ' .  escapeshellarg($width) . ' ' .  escapeshellarg($newFilename));
}

function runExternal($cmd)
{
	$cmd .= ' 2>&1';
	$output = array();
	$status = -1;

	exec($cmd, $output, $status);
	
	if ($status !== 0 || count($output) > 0)
	{
		return array(
			'cmd'     => $cmd,
			'output'  => $output,
			'status'  => $status,
		);
	}
	
	return true;
}

// TODO: user system
$__USER_ID = '2';
?>
