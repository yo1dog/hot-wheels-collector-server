<?php
session_start();

/**
 * Auth Manager
 * 
 * Provides functions for authenticating and retriving the user.
 */
class AuthManager
{
	private static $loggedInUserID = NULL;
	private static $loginChecked = false;
	
	/**
	 * Get Logged In User
	 * 
	 * Returns the currently logged in user.
	 * 
	 * @return string|null ID of the currently logged in user or NULL if there is no logged in user.
	 */
	public static function getLoggedInUser()
	{
		if (!self::$loginChecked)
		{
			if (isset($_SESSION['loggedInUserID']))
				self::$loggedInUserID = $_SESSION['loggedInUserID'];
			
			if (self::$loggedInUserID === NULL)
				self::$loggedInUserID = '2';
			
			self::$loginChecked = true;
		}
		
		return self::$loggedInUserID;
	}
	
	
	public static function login()
	{
		$_SESSION['loggedInUserID'] = '1';
	}
	
	public static function logout()
	{
		unset($_SESSION['loggedInUserID']);
	}
}
?>
