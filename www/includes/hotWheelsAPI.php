<?php
define('HOTWHEELS_SEARCH_ENDPOINT_URL', 'http://www.hotwheels.com/CarCollectionsRWD/DisplaySearchVehicles');
define('HOTWHEELS_BASE_IMAGE_URL',      'http://www.hotwheels.com');

class Car
{
	public $vehicleID;
	public $name;
	public $toyNumber;
	public $segment;
	public $series;
	public $make;
	public $color;
	public $style;
	public $numUsersCollected;

	private $imageURLBeforeWidth;
	private $imageURLAfterWidth;
	
	public function __construct(
		$vehicleID,
		$name,
		$toyNumber,
		$segment,
		$series,
		$make,
		$color,
		$style,
		$numUsersCollected,
		
		$imagePath)
	{
		$this->vehicleID         = $vehicleID;
		$this->name              = $name;
		$this->toyNumber         = $toyNumber;
		$this->segment           = $segment;
		$this->series            = $series;
		$this->make              = $make;
		$this->color             = $color;
		$this->style             = $style;
		$this->numUsersCollected = $numUsersCollected;
		
		// split the url on the image width
		$index1 = strrpos($imagePath, '_w') + 2;
		$index2 = NULL;		
		
		for ($i = $index1; $i < strlen($imagePath); ++$i)
		{
			$char = ord($imagePath[$i]);
			
			if ($char < 48 || $char > 57)
			{
				$index2 = $i;
				break;
			}
		}
		
		$this->imageURLBeforeWidth = HOTWHEELS_BASE_IMAGE_URL . substr($imagePath, 0, $index1);
		$this->imageURLAfterWidth = $index2 === NULL? '' : substr($imagePath, $index2);
	}
	
	public function getImageURL($width)
	{
		return $this->imageURLBeforeWidth . $width . $this->imageURLAfterWidth;
	}
}


class HotWheelsAPI
{
	private static function decodeHTMLText($str)
	{
		$str = trim($str);
		$str = html_entity_decode($str);
		$str = preg_replace('/&#(\d+);/me', 'chr(\\1)', $str);
		$str = preg_replace('/&#x([a-f0-9]+);/mei', 'chr(0x\\1)', $str);
		
		return $str;
	}
	
	private static function parseSection($str, &$index, $startStr, $endStr, $lookingFor)
	{
		$index  = self::requiredStrpos($str, $startStr, $index, $lookingFor) + strlen($startStr);
		$index2 = strpos($str, $endStr, $index);
		
		return self::decodeHTMLText(substr($str, $index, $index2 - $index));
	}
	
	private static function requiredStrpos($haystack, $needle, $offset, $lookingFor)
	{
		$index = strpos($haystack, $needle, $offset);
		
		if ($index === false)
			throw new Exception("Unable to find \"$needle\" while looking for $lookingFor.");
		
		return $index;
	}
		

	/**
	 * Uses the HotWheels site search endpoint and parses the HTML response into a list of car detail URLs.
	 *
	 * Returns an array of car detail URLs on success or thows an exception.
	 */
	public static function search($query, $cURLTimeout = 30)
	{
		// create cURL request
		$postFields = array(
			'searchtext' => $query,
			'pubId'      => '838',
			'locale'     => 'en-us');
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,            HOTWHEELS_SEARCH_ENDPOINT_URL);
		curl_setopt($ch, CURLOPT_POST,           count($postFields));
		curl_setopt($ch, CURLOPT_POSTFIELDS,     $postFields);
		curl_setopt($ch, CURLOPT_TIMEOUT,        $cURLTimeout);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		
		// execute cURL request
		$cURLResult = curl_exec($ch);
		
		// check for cURL errors
		$cURLErrorNum = curl_errno($ch);
		if ($cURLErrorNum !== 0)
			throw new Exception('cURL Error (' . $cURLErrorNum . '): ' . curl_error($ch));
		
		if ($cURLResult === false)
			throw new Exception('cURL Error: unknown');
		
		$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($statusCode !== 200)
			throw new Exception('Non-200 response status code: "' . $statusCode . '"');
		
		
		// parse out the car detail URLs
		$carDetailURLs = array();
		
		$index = 0;
		while (($index = strpos($cURLResult, 'href="', $index)) !== false)
		{
			$index += 6;
			$index2 = strpos($cURLResult, '"', $index);
			$carDetailURLs[] = substr($cURLResult, $index, $index2 - $index);
		}
		
		return $carDetailURLs;
	}
	
	
	/**
	 * Uses the HotWheels site details endpoint and parses the HTML response into a Car Model.
	 *
	 * Returns a Car Model on success or throws an exception.
	 */
	public static function getCar($carDetailURL, $cURLTimeout = 30)
	{
		// create cURL request
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,            $carDetailURL);
		curl_setopt($ch, CURLOPT_TIMEOUT,        $cURLTimeout);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		
		// execute cURL request
		$cURLResult = curl_exec($ch);
		
		// check for cURL errors
		$cURLErrorNum = curl_errno($ch);
		if ($cURLErrorNum !== 0)
			throw new Exception('cURL Error (' . $cURLErrorNum . '): ' . curl_error($ch));
		
		if ($cURLResult === false)
			throw new Exception('cURL Error: unknown');
		
		$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($statusCode !== 200)
		{
			if ($statusCode === 404 || $statusCode === 500) // they poorly handle invalid/non-existant ids and causes a 500
				throw new Exception('Car not found');

			throw new Exception('Non-200 response status code: "' . $statusCode . '"');
		}

		
		// parse the car
		$index = 0;
		
		// ::::: CAR IMAGE ::::
		// ...
		// background-image: url(/en-us/Images/V5328_Toyota_Tundra_tcm838-123677_w351.png);
		$index = self::requiredStrpos($cURLResult, '::::: CAR IMAGE ::::', $index, 'imagePath');
		
		$imagePath = self::parseSection($cURLResult, $index, 'url(', ')', 'imagePath');
		
		// <a id="wantButton" class="btn btn-med " href="javascript:void(0)" data-action="wantit" carTitle="&#39;10 Toyota Tundra" mainImageUrl ="" vehicleId="tcm:838-123678" carId="V5328" wantHave="Want" segment="2012 New Models" series="" make="Toyota" color="Black" style="Truck" segmentColor="" ><span class="icon icon-star"></span>Want It</a>
		$name      = self::parseSection($cURLResult, $index, 'carTitle="',  '"', 'name');
		$vehicleID = self::parseSection($cURLResult, $index, 'vehicleId="', '"', 'vehicleID');
		$toyNumber = self::parseSection($cURLResult, $index, 'carId="',     '"', 'toyNumber');
		$segment   = self::parseSection($cURLResult, $index, 'segment="',   '"', 'segment');
		$series    = self::parseSection($cURLResult, $index, 'series="',    '"', 'series');
		$make      = self::parseSection($cURLResult, $index, 'make="',      '"', 'make');
		$color     = self::parseSection($cURLResult, $index, 'color="',     '"', 'color');
		$style     = self::parseSection($cURLResult, $index, 'style="',     '"', 'style');
		
		// <li><span class="label">Collected:</span> <span class="value">13606</span></li>
		$index = strpos($cURLResult, 'Collected:', $index);
		$numUsersCollected = trim(self::parseSection($cURLResult, $index, 'value">', '<', 'numUsersCollected'));
		
		if (!is_numeric($numUsersCollected))
			$numUsersCollected = NULL;
		else
			$numUsersCollected = intval($numUsersCollected);
		
		
		return new Car(
			$vehicleID,
			$name,
			$toyNumber,
			$segment,
			$series,
			$make,
			$color,
			$style,
			$numUsersCollected,
			
			$imagePath);
	}
}
?>
