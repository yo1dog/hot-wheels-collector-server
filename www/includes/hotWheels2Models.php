<?php
class HW2Car
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
	
	private $sortName;
	private $imageName;
	
	public $imageURL;
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
		
		$this->ownedTimestamp = $assoc['ownedTimestamp'] === NULL? NULL : intval($assoc['ownedTimestamp']);
		
		$this->sortName  = $assoc['sort_name'];
		$this->imageName = $assoc['image_name'];
		
		$this->imageURL       = $assoc['image_name'] === NULL? NULL : HOTWHEELS2_BASE_IMAGE_URL . $assoc['image_name'] . HOTWHEELS2_IMAGE_ICON_SUFFIX   . ($this->isCustom? HOTWHEELS2_IMAGE_CUSTOM_EXT : HOTWHEELS2_IMAGE_EXT);
		$this->detailImageURL = $assoc['image_name'] === NULL? NULL : HOTWHEELS2_BASE_IMAGE_URL . $assoc['image_name'] . HOTWHEELS2_IMAGE_DETAIL_SUFFIX . ($this->isCustom? HOTWHEELS2_IMAGE_CUSTOM_EXT : HOTWHEELS2_IMAGE_EXT);
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
}
?>
