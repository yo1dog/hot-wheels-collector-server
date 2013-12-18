<?php
define('HOTWHEELS_SEARCH_ENDPOINT_URL', 'http://www.hotwheels.com/CarCollections/DisplaySearchVehicles');
define('HOTWHEELS_DETAIL_ENDPOINT_URL', 'http://www.hotwheels.com/CarCollections/GetVehicleDetail');
define('HOTWHEELS_BASE_IMAGE_URL',      'http://www.hotwheels.com');

class Car
{
	public $id;
	public $name;
	public $toyNumber;
	public $imagePath;
	public $imageURL;
	public $owned;
	
	public function __construct($id, $name, $toyNumber, $imagePath)
	{
		$this->id        = $id;
		$this->name      = $name;
		$this->toyNumber = $toyNumber;
		$this->imagePath = $imagePath;
		$this->imageURL  = HOTWHEELS_BASE_IMAGE_URL . $imagePath;
	}
}

class CarDetails extends Car
{
	public $detailImagePath;
	public $detailImageURL;
	public $segment;
	public $series;
	public $carNumber;
	public $color;
	public $make;
	
	public function __construct($id, $name, $toyNumber, $detailImagePath, $segment, $series, $carNumber, $color, $make)
	{
		$imagePath = substr($detailImagePath, 0, strlen($detailImagePath) - 15) . 'chicklet_none.png';
		
		parent::__construct($id, $name, $toyNumber, $imagePath);
		
		$this->segment         = $segment;
		$this->series          = $series;
		$this->carNumber       = $carNumber;
		$this->color           = $color;
		$this->make            = $make;
		$this->detailImagePath = $detailImagePath;
		$this->detailImageURL  = HOTWHEELS_BASE_IMAGE_URL . $detailImagePath;
	}
}


class HotWheelsAPI
{
	private static function parseTagContents($str)
	{
		$str = trim($str);
		$str = html_entity_decode($str);
		$str = preg_replace('/&#(\d+);/me', 'chr(\\1)', $str);
		$str = preg_replace('/&#x([a-f0-9]+);/mei', 'chr(0x\\1)', $str);
		
		return $str;
	}
	
	
	/**
	 * Uses the HotWheels site search endpoint and parses the HTML response into Car Search Result Models.
	 *
	 * Returns an array of Car Search Result Models on success or a string containing an error message.
	 */
	public static function search($query, $cURLTimeout = 30)
	{
		// create cURL request
		$postFields = array(
			'searchtext' => $query,
			'pubId'      => '437',
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
		
		// parse out the cars
		$cars = array();
		
		$index = 0;
		while (($index = strpos($cURLResult, 'cars=', $index)) !== false)
		{
			$index += 6;
			$index2 = strpos($cURLResult, '"', $index);
			$toyNumber = substr($cURLResult, $index, $index2 - $index);
			
			$index = strpos($cURLResult, 'src=', $index) + 5;
			$index2 = strpos($cURLResult, '"', $index);
			$imagePath = substr($cURLResult, $index, $index2 - $index);
			
			$index = strpos($cURLResult, 'carId=', $index) + 7;
			$index2 = strpos($cURLResult, '"', $index);
			$id = substr($cURLResult, $index, $index2 - $index);
			
			$index = strpos($cURLResult, '>', $index) + 1;
			$index2 = strpos($cURLResult, '<', $index);
			$name = self::parseTagContents(substr($cURLResult, $index, $index2 - $index));
			
			$cars[] = new Car($id, $name, $toyNumber, $imagePath);
		}
		
		return $cars;
	}
	
	
	/**
	 * Uses the HotWheels site details endpoint and parses the HTML response into a Car Model.
	 *
	 * Returns a Car Model on success or a string containing an error message.
	 */
	public static function getCarDetails($carID)
	{
		// create cURL request
		$postFields = array(
			'vehicleId'    => $carID,
			'segmentColor' => '#929392',
			'locale'       => 'en-us');
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,            HOTWHEELS_DETAIL_ENDPOINT_URL);
		curl_setopt($ch, CURLOPT_POST,           count($postFields));
		curl_setopt($ch, CURLOPT_POSTFIELDS,     $postFields);
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
		
		if (strlen($cURLResult) === 0)
			return 'Car not found';
		
		$index = strpos($cURLResult, 'src=') + 5;
		$index2 = strpos($cURLResult, '"', $index);
		$imagePath = substr($cURLResult, $index, $index2 - $index);
		
		$index = strpos($cURLResult, '<h2', $index);
		$index = strpos($cURLResult, '>', $index) + 1;
		$index2 = strpos($cURLResult, '<', $index);
		$name = self::parseTagContents(substr($cURLResult, $index, $index2 - $index));
		
		$index = strpos($cURLResult, 'Segment:');
		if ($index !== false)
		{
			$index = strpos($cURLResult, 'car_detail_value', $index) + 18;
			$index2 = strpos($cURLResult, '<', $index);
			$segment =  self::parseTagContents(substr($cURLResult, $index, $index2 - $index));
		}
		else
			$segment = '';
		
		$index = strpos($cURLResult, 'Series:');
		if ($index !== false)
		{
			$index = strpos($cURLResult, 'car_detail_value', $index) + 18;
			$index2 = strpos($cURLResult, '<', $index);
			$series =  self::parseTagContents(substr($cURLResult, $index, $index2 - $index));
		}
		else
			$series = '';
		
		$index = strpos($cURLResult, 'Car Number:');
		if ($index !== false)
		{
			$index = strpos($cURLResult, 'car_detail_value', $index) + 18;
			$index2 = strpos($cURLResult, '<', $index);
			$carNumber =  self::parseTagContents(substr($cURLResult, $index, $index2 - $index));
		}
		else
			$carNumber = '';
		
		$index = strpos($cURLResult, 'Color:');
		if ($index !== false)
		{
			$index = strpos($cURLResult, 'car_detail_value', $index) + 18;
			$index2 = strpos($cURLResult, '<', $index);
			$color =  self::parseTagContents(substr($cURLResult, $index, $index2 - $index));
		}
		else
			$color = '';
		
		$index = strpos($cURLResult, 'Make:');
		if ($index !== false)
		{
			$index = strpos($cURLResult, 'car_detail_value', $index) + 18;
			$index2 = strpos($cURLResult, '<', $index);
			$make =  self::parseTagContents(substr($cURLResult, $index, $index2 - $index));
		}
		else
			$make = '';
		
		$index = strpos($cURLResult, 'Toy Number:');
		if ($index !== false)
		{
			$index = strpos($cURLResult, 'car_detail_value', $index) + 18;
			$index2 = strpos($cURLResult, '<', $index);
			$toyNumber =  self::parseTagContents(substr($cURLResult, $index, $index2 - $index));
		}
		else
			$toyNumber = '';
		
		return new CarDetails($carID, $name, $toyNumber, $imagePath, $segment, $series, $carNumber, $color, $make);
	}
}
?>