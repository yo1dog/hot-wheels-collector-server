<?php

define('HOTWHEELS_SEARCH_ENDPOINT_URL', 'http://www.hotwheels.com/CarCollectionsRWD/DisplaySearchVehicles');
define('HOTWHEELS_BASE_IMAGE_URL',      'http://www.hotwheels.com');

/**
 * Represents a car from the Hot Wheels website.
 */
class HotWheelsCar
{
	public $vehicleID;
	public $name;
	public $toyNumber;
	public $segment;
	public $series;
	public $make;
	public $color;
	public $carNum;
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
		$carNum,
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
		$this->carNum            = $carNum;
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
			
			$detailURL = substr($searchPage, $index, $index2 - $index);
			
			$collectionStr = "collection/";
			$collectionStrIndex = strpos($detailURL, $collectionStr);
			if ($collectionStrIndex === false)
				continue;
			
			$collectionStrIndex += strlen($collectionStr);
			$detailURL = substr($detailURL, 0, $collectionStrIndex) . rawurlencode(substr($detailURL, $collectionStrIndex));
			
			$carDetailURLs[] = $detailURL;
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
		
    // <input type="hidden" id="hdnTCMURI" value="tcm:838-123727-16" />
		$vehicleID = self::parseSection($carDetailPage, $index, 'id="hdnTCMURI" value="', '"', 'vehicleID');
    
		// ::::: CAR IMAGE ::::
		// ...
		// background-image: url(/en-us/Images/V5328_Toyota_Tundra_tcm838-123677_w351.png);
		$index = self::requiredStrpos($carDetailPage, '::::: CAR IMAGE ::::', $index, 'imagePathStart');
		$imagePath = self::parseSection($carDetailPage, $index, 'url(', ')', 'imagePath');
		
		// <h2 class="name">’85 Chevrolet Camaro IROC-Z</h2>
		$name = self::parseSection($carDetailPage, $index, '<h2 class="name">', '</h2>', 'name');
		
		/*
		<li><span class="label">Collected:</span> <span class="value">28875</span></li>
		<li>
		<span class="label">Segment:</span>
		<span class="value"> HW Showroom™</span></li>
		<li><span class="label">Make:</span><span class="value"> Volkswagen</span></li>
		<li><span class="label">Car #:</span><span class="value"> 160</span></li>  
		<li><span class="label">Color:</span><span class="value"> Blue</span></li>
		<li><span class="label">Series:</span><span class="value">  HW ASPHALT ASSAULT™</span></li>
        */
        $index = self::requiredStrpos($carDetailPage, '<span class="label">Collected:</span>', $index, 'numUsersCollectedStart');
		$numUsersCollectedStr = self::parseSection($carDetailPage, $index, '<span class="value">', '</span>', 'numUsersCollected');
		
		$index = self::requiredStrpos($carDetailPage, '<span class="label">Segment:</span>', $index, 'segmentStart');
		$segment = self::parseSection($carDetailPage, $index, '<span class="value">', '</span>', 'segment');
		
		$index = self::requiredStrpos($carDetailPage, '<span class="label">Make:</span>', $index, 'makeStart');
		$make = self::parseSection($carDetailPage, $index, '<span class="value">', '</span>', 'make');
		
		$index = self::requiredStrpos($carDetailPage, '<span class="label">Car #:</span>', $index, 'carNumStart');
		$carNumStr = self::parseSection($carDetailPage, $index, '<span class="value">', '</span>', 'carNum');
		
		$index = self::requiredStrpos($carDetailPage, '<span class="label">Color:</span>', $index, 'colorStart');
		$color = self::parseSection($carDetailPage, $index, '<span class="value">', '</span>', 'color');
		
		$series = NULL;
		$posIndex = strpos($carDetailPage, '<span class="label">Series:</span>', $index);
		if ($posIndex !== false) {
		  $index = $posIndex;
		  $series = self::parseSection($carDetailPage, $index, '<span class="value">', '</span>', 'series');
		}
		
    // <a class="remove_anchor" rel="nofollow" href="javascript:void(0);"  carId="V5310" onclick="removeCollectionFunction(this);"><span class="remove_btn">Remove From Your Collection</span>
		$toyNumber = self::parseSection($carDetailPage, $index, 'carId="', '"', 'toyNumber');
		
		// parse strings as ints
		$numUsersCollected = NULL;
		$numUsersCollectedStr = trim($numUsersCollectedStr);
		if (is_numeric($numUsersCollectedStr))
			$numUsersCollected = intval($numUsersCollectedStr);
		
		$carNum = NULL;
		$carNumStr = trim($carNumStr);
		if (is_numeric($carNumStr))
			$carNum = intval($carNumStr);
		
		return new HotWheelsCar(
			$vehicleID,
			$name,
			$toyNumber,
			$segment,
			$series,
			$make,
			$color,
			$carNum,
			$numUsersCollected,
			
			$imagePath);
	}
}
?>
