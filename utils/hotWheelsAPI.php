<?php

define('HOTWHEELS_SEARCH_ENDPOINT_URL', 'http://www.hotwheels.com/CarCollectionsRWD/DisplaySearchVehicles');
define('HOTWHEELS_BASE_IMAGE_URL',      'http://www.hotwheels.com');

/**
 * Represents a car from the Hot Wheels website.
 */
class HotWheelsCar
{
	/** @var string   */ public $vehicleID;
	/** @var string   */ public $name;
	/** @var string   */ public $toyNumber;
	/** @var string   */ public $segment;
	/** @var string   */ public $series;
	/** @var string   */ public $make;
	/** @var string   */ public $color;
	/** @var string   */ public $style;
	/** @var int|null */ public $numUsersCollected;

	/** @var string   */ private $imageURLBeforeWidth;
	/** @var string   */ private $imageURLAfterWidth;
	
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
		$index2 = $index1;
		
		for (; $index2 < strlen($imagePath); ++$index2)
		{
			$char = ord($imagePath[$index2]);
			
			if ($char < 48 || $char > 57)
				break;
		}
		
		$this->imageURLBeforeWidth = HOTWHEELS_BASE_IMAGE_URL . substr($imagePath, 0, $index1);
		
		$after = substr($imagePath, $index2);
		if ($after === false)
			$after = '';
		
		$this->imageURLAfterWidth = $after ;
	}
	
	
	/**
	* Creates an image URL with the given width.
	*/
	public function getImageURL($width)
	{
		return $this->imageURLBeforeWidth . $width . $this->imageURLAfterWidth;
	}
}


class HotWheelsAPI
{
	/**
	 * Converts HTML hex strings into characters.
	 */
	private static function decodeHTMLText($str)
	{
		$str = trim($str);
		$str = html_entity_decode($str);
		$str = preg_replace('/&#(\\d+);/me', 'chr(\\1)', $str);
		$str = preg_replace('/&#x([a-f0-9]+);/mei', 'chr(0x\\1)', $str);
		
		return $str;
	}
	
	private static function requiredStrpos($haystack, $needle, $offset, $lookingFor)
	{
		$index = strpos($haystack, $needle, $offset);
		
		if ($index === false)
			throw new Exception("Unable to find \"$needle\" while looking for $lookingFor.");
		
		return $index;
	}
	
	private static function parseSection($str, &$index, $startStr, $endStr, $lookingFor)
	{
		$index  = self::requiredStrpos($str, $startStr, $index, $lookingFor) + strlen($startStr);
		$index2 = self::requiredStrpos($str, $endStr,   $index, $lookingFor);
		
		return self::decodeHTMLText(substr($str, $index, $index2 - $index));
	}
		

	/**
	 * Uses the Hot Wheels site search endpoint and parses the HTML response into a list of car detail URLs.
	 *
	 * @param string $query       String to search with.
	 * @param int    $cURLTimeout Timeout limit of the cURL request in seconds.
	 *
	 * @return string[] An array of car detail URLs.
	 * @throws Exception on error.
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
		
		$searchPage = $cURLResult;
		
		// parse out the car detail URLs
		$carDetailURLs = array();
		
		$index = 0;
		while (($index = strpos($searchPage, 'href="', $index)) !== false)
		{
			$index += 6;
			$index2 = strpos($searchPage, '"', $index);
			$carDetailURLs[] = substr($searchPage, $index, $index2 - $index);
		}
		
		return $carDetailURLs;
	}
	
	
	/**
	 * Uses the Hot Wheels site details endpoint and parses the HTML response into a Car Model.
	 * 
	 * @param string $carDetailURL Detail URL of the car.
	 * @param int    $cURLTimeout  Timeout limit of the cURL request in seconds.
	 * 
	 * @return HotWheelsCar
	 * @throws Exception
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
		
		$carDetailPage = $cURLResult;

		
		// parse the car
		$index = 0;
		
		// ::::: CAR IMAGE ::::
		// ...
		// background-image: url(/en-us/Images/V5328_Toyota_Tundra_tcm838-123677_w351.png);
		$index = self::requiredStrpos($carDetailPage, '::::: CAR IMAGE ::::', $index, 'imagePath');
		
		$imagePath = self::parseSection($carDetailPage, $index, 'url(', ')', 'imagePath');
		
		// <a id="wantButton" class="btn btn-med " href="javascript:void(0)" data-action="wantit" carTitle="&#39;10 Toyota Tundra" mainImageUrl ="" vehicleId="tcm:838-123678" carId="V5328" wantHave="Want" segment="2012 New Models" series="" make="Toyota" color="Black" style="Truck" segmentColor="" ><span class="icon icon-star"></span>Want It</a>
		$name      = self::parseSection($carDetailPage, $index, 'carTitle="',  '"', 'name');
		$vehicleID = self::parseSection($carDetailPage, $index, 'vehicleId="', '"', 'vehicleID');
		$toyNumber = self::parseSection($carDetailPage, $index, 'carId="',     '"', 'toyNumber');
		$segment   = self::parseSection($carDetailPage, $index, 'segment="',   '"', 'segment');
		$series    = self::parseSection($carDetailPage, $index, 'series="',    '"', 'series');
		$make      = self::parseSection($carDetailPage, $index, 'make="',      '"', 'make');
		$color     = self::parseSection($carDetailPage, $index, 'color="',     '"', 'color');
		$style     = self::parseSection($carDetailPage, $index, 'style="',     '"', 'style');
		
		// <li><span class="label">Collected:</span> <span class="value">13606</span></li>
		$index = strpos($carDetailPage, 'Collected:', $index);
		$numUsersCollectedStr = trim(self::parseSection($carDetailPage, $index, 'value">', '<', 'numUsersCollected'));
		
		$numUsersCollected = NULL;
		if (is_numeric($numUsersCollectedStr))
			$numUsersCollected = intval($numUsersCollectedStr);
		
		
		return new HotWheelsCar(
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
