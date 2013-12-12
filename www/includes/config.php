<?php
set_exception_handler('exception_handler');

function exception_handler($exception)
{
	echo $exception->getMessage(), ' in ', $exception->getFile(), ':', $exception->getCode();
}
?>