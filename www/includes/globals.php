<?php
// exception handler
set_exception_handler('exception_handler');

function exception_handler($exception)
{
	http_response_code(500);
	throw $exception;
}

// TODO: user system
$__USER_ID = '1';
?>
