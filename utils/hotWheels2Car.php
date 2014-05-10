<?php
define('IMAGE_NAME_TRUNCATE_LENGTH', 32);

//TODO: search for "details" and reaplce with "detail" (image)
require_once __DIR__ . '/imageManager.php';

class HotWheels2Car
{
	public $id;
	public $vehicleID;
	public $name;
	public $toyNumber;
	public $segment;
	public $series;
	public $make;
	public $color;
	public $style;
	public $numUsersCollected;
	public $isCustom;
	public $customToyNumber;
	public $distinguishingNotes;
	public $barcodeData;
	
	public $ownedTimestamp;
	
	public $sortName;
	private $imageName;
	
	public $iconImageURL;
	public $detailImageURL;
	
	public function __construct($assoc)
	{
		$this->id                  = $assoc['id'];
		$this->vehicleID           = $assoc['vehicle_id'];
		$this->name                = $assoc['name'];
		$this->toyNumber           = $assoc['toy_number'];
		$this->segment             = $assoc['segment'];
		$this->series              = $assoc['series'];
		$this->make                = $assoc['make'];
		$this->color               = $assoc['color'];
		$this->style               = $assoc['style'];
		$this->numUsersCollected   = $assoc['num_users_collected'] === NULL? NULL : intval($assoc['num_users_collected']);
		$this->isCustom            = $assoc['is_custom'] === '1';
		$this->customToyNumber     = $assoc['custom_toy_number'];
		$this->distinguishingNotes = $assoc['distinguishing_notes'];
		$this->barcodeData         = $assoc['barcode_data'];
		
		$this->ownedTimestamp = !isset($assoc['ownedTimestamp']) || $assoc['ownedTimestamp'] === NULL? NULL : intval($assoc['ownedTimestamp']);
		
		$this->sortName  = $assoc['sort_name'];
		$this->imageName = $assoc['image_name'];
		
		
		if ($this->imageName === NULL)
		{
			$this->iconImageURL   = NULL;
			$this->detailImageURL = NULL;
		}
		else
		{
			$this->iconImageURL   = ImageManager::getImageURL($this->imageName, CAR_IMAGE_TYPE_ICON,   $this->isCustom);
			$this->detailImageURL = ImageManager::getImageURL($this->imageName, CAR_IMAGE_TYPE_DETAIL, $this->isCustom);
		}
	}
	
	
	public function diff($car)
	{
		$diffFields = array();
		
		foreach ($car as $key => $value)
		{
			if ($value !== $this->$key)
				$diffFields[$key] = array('from' => $this->$key, 'to' => $value);
		}
		
		return $diffFields;
	}
	
	public static function createCarImageName($carID, $carName)
	{
		$imageName = preg_replace('/[^a-zA-Z0-9 ]/', '', $carName);
		
		if (strlen($imageName) > IMAGE_NAME_TRUNCATE_LENGTH)
			$imageName = substr($imageName, 0, IMAGE_NAME_TRUNCATE_LENGTH);
		
		$imageName = str_replace(' ', '_', strtolower($imageName));
		
		return $carID . '_' . $imageName;
	}
	
	public static function createCarSortName($carName)
	{
		$sortName = strtolower($carName);
		$sortName = preg_replace('/[^a-z0-9 ]/', '', $sortName);
		
		if (preg_match('/^[0-9]+s/', $sortName))
		{
			$index = strpos($sortName, 's');
			$sortName = substr($sortName, 0, $index) . substr($sortName, $index + 1);
		}
		
		if (strpos($sortName, 'the ') === 0)
			$sortName = substr($sortName, 4);
		
		$sortName = str_replace(' ', '', $sortName);
		
		$matches = NULL;
		if (preg_match('/^[0-9]+/', $sortName, $matches))
		{
			if (count($matches) > 0)
			{
				$yearStr = $matches[0];			
				$sortName = substr($sortName, strlen($yearStr)) . ' ' . $yearStr;
			}
		}
		
		return $sortName;
	}
}
?>
