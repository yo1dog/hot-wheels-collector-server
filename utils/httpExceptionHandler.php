<?php
// exception handler
class HTTPException extends Exception
{
	public $httpStatusCode;
	
	public function __construct($httpStatusCode, $message)
	{
		parent::__construct($message);
		
		$this->httpStatusCode = $httpStatusCode;
	}
}


function customErrorHandler($errno, $errstr, $errfile, $errline )
{
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}
set_error_handler("customErrorHandler");

function customExceptionHandler($exception)
{
	if (isset($exception->httpStatusCode))
		http_response_code($exception->httpStatusCode);
	else
		http_response_code(500);
	
	header('Content-type: text/plain');
	throw $exception;
};
set_exception_handler('customExceptionHandler');
?>
