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

// TODO: user system
$__USER_ID = '1';
?>
