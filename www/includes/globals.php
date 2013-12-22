<?php
// exception handler
set_exception_handler('exception_handler');

function exception_handler($exception)
{
	http_response_code(500);	
	echo $exception->getMessage(), ' in ', $exception->getFile(), ':', $exception->getCode();
}

// TODO: user system
$__USER_ID = '1';
?>
