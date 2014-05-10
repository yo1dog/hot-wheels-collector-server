<?php
/**
 * External Process Result
 *
 * The result of an executed external process.
 */
class ExternalProcessResult
{
	public $cmd;
	public $output;
	public $status;
	
	public function __construct($cmd, $output, $status)
	{
		$this->cmd    = $cmd;
		$this->output = $output;
		$this->status = $status;
	}
}


/**
 * External Processor
 * 
 * Provides functions for generating images, copying images to S3, and retrieving image filenames.
 */
class ExternalProcessor
{
	/**
	 * Run External
	 *
	 * Runs an external process and captures the output.
	 * 
	 * @param string $cmd Command to run.
	 * 
	 * @return object cmd:    Command ran
	 *                output: Output of the process
	 *                status: Return status of the process
	 */
	public static function executeExternalProcess($cmd)
	{
		$cmd .= ' 2>&1';
		$output = array();
		$status = -1;
		
		exec($cmd, $output, $status);
		
		return new ExternalProcessResult($cmd, $output, $status);
	}
}
?>