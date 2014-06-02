<?php
/**
 * Auth Manager
 * 
 * Provides functions for authenticating and retriving the user.
 */
class AuthManager
{
	private static $loggedInUserID = NULL;
	
	/**
	 * Get Logged In User
	 * 
	 * Returns the currently logged in user.
	 * 
	 * @return string|null ID of the currently logged in user or NULL if there is no logged in user.
	 */
	public static function getLoggedInUser()
	{
		return '1';//self::$loggedInUserID;
	}
}
?>
