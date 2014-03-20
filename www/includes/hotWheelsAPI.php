<?php
define('HOTWHEELS_SEARCH_ENDPOINT_URL', 'http://www.hotwheels.com/CarCollectionsRWD/DisplaySearchVehicles');
define('HOTWHEELS_BASE_IMAGE_URL',      'http://www.hotwheels.com');

class Car
{
	public $id;
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
		$id,
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
		$this->id                = $id;
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
	
	private static function parseSection($str, &$index, $startStr, $endStr)
	{
		$index  = strpos($str, $startStr, $index) + strlen($startStr);
		$index2 = strpos($str, $endStr,   $index);
		
		return substr($str, $index, $index2 - $index);
	}
		

	/**
	 * Uses the HotWheels site search endpoint and parses the HTML response into a list of car detail URLs.
	 *
	 * Returns an array of car detailURLs on success or a string containing an error message.
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
			return 'cURL Error (' . $cURLErrorNum . '): ' . curl_error($ch);
		
		if ($cURLResult === false)
			return 'cURL Error: unknown';
		
		$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($statusCode !== 200)
			return 'Request Error: Status code ' . $statusCode;
		
		
		// parse out the car ids
		$carDetailURLs = array();
		
		$index = 0;
		while (($index = strpos($cURLResult, 'href="', $index)) !== false)
		{
			$index += 6;
			$index2 = strpos($cURLResult, '"', $index);
			$carDetailURLs[] = substr($cURLResult, $index, $index2 - $index);
		}
		
		return $carDetailURLs;
		
		
		/*
		$cars = array();
		
		$index = 0;
		while (($index = strpos($cURLResult, 'cars=', $index)) !== false)
		{
			$index += 6;
			$index2 = strpos($cURLResult, '"', $index);
			$toyNumber = substr($cURLResult, $index, $index2 - $index);
			
			$index = strpos($cURLResult, 'carId=', $index) + 7;
			$index2 = strpos($cURLResult, '"', $index);
			$id = substr($cURLResult, $index, $index2 - $index);
			
			$index = strpos($cURLResult, 'src=', $index) + 5;
			$index2 = strpos($cURLResult, '"', $index);
			$imagePath = substr($cURLResult, $index, $index2 - $index);
			
			$index = strpos($cURLResult, 'title">', $index) + 7;
			$index2 = strpos($cURLResult, '<', $index);
			$name = self::decodeHTMLText(substr($cURLResult, $index, $index2 - $index));
			
			$cars[] = new Car($id, $name, $toyNumber, $imagePath);
		}
		
		return $cars;
		*/
	}
	
	
	/**
	 * Uses the HotWheels site details endpoint and parses the HTML response into a Car Model.
	 *
	 * Returns a Car Model on success or a string containing an error message.
	 */
	public static function getCarDetails($carDetailURL, $cURLTimeout = 30)
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
			return 'cURL Error (' . $cURLErrorNum . '): ' . curl_error($ch);
		
		if ($cURLResult === false)
			return 'cURL Error: unknown';
		
		$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($statusCode !== 200)
		{
			if ($statusCode === 404 || $statusCode === 500) // they poorly handle invalid/non-existant ids and causes a 500
				return 'Car not found';

			return 'Request Error: Status code ' . $statusCode;
		}

		
		// parse the car
		$index = 0;
		
		// ::::: CAR IMAGE ::::
		// ...
		// background-image: url(/en-us/Images/V5328_Toyota_Tundra_tcm838-123677_w351.png);
		$index = strpos($cURLResult, '::::: CAR IMAGE ::::', $index);
		$imagePath = self::parseSection($cURLResult, $index, 'url(', ')');
		
		// <a id="wantButton" class="btn btn-med " href="javascript:void(0)" data-action="wantit" carTitle="&#39;10 Toyota Tundra" mainImageUrl ="" vehicleId="tcm:838-123678" carId="V5328" wantHave="Want" segment="2012 New Models" series="" make="Toyota" color="Black" style="Truck" segmentColor="" ><span class="icon icon-star"></span>Want It</a>
		$name      = self::decodeHTMLText(
		             self::parseSection($cURLResult, $index, 'carTitle="',  '"'));
		$id        = self::parseSection($cURLResult, $index, 'vehicleId="', '"');
		$toyNumber = self::parseSection($cURLResult, $index, 'carId="',     '"');
		$segment   = self::parseSection($cURLResult, $index, 'segment="',   '"');
		$series    = self::parseSection($cURLResult, $index, 'series="',    '"');
		$make      = self::parseSection($cURLResult, $index, 'make="',      '"');
		$color     = self::parseSection($cURLResult, $index, 'color="',     '"');
		$style     = self::parseSection($cURLResult, $index, 'style="',     '"');
		
		// <li><span class="label">Collected:</span> <span class="value">13606</span></li>
		$index = strpos($cURLResult, 'Collected:', $index);
		$numUsersCollected = trim(self::parseSection($cURLResult, $index, 'value">', '<'));
		
		if (!is_numeric($numUsersCollected))
			$numUsersCollected = NULL;
		else
			$numUsersCollected = intval($numUsersCollected);
		
		
		return new Car(
			$id,
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
