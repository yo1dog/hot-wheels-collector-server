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

function createCarImageName($carVehicleID)
{
	return preg_replace('/[^a-zA-Z0-9]/', '_', $carVehicleID);
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

// TODO: user system
$__USER_ID = '2';
?>
