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
	
	public $owned;
	
	public $imageURL;
	public $detailImageURL;
	
	public function __construct($id, $vehicleID, $name, $toyNumber, $segment, $series, $make, $color, $style, $numUsersCollected, $imageName, $sortName, $owned)
	{
		$this->id                = $id;
		$this->vehicleID         = $vehicleID;
		$this->name              = $name;
		$this->toyNumber         = $toyNumber;
		$this->segment           = $segment;
		$this->series            = $series;
		$this->make              = $make;
		$this->color             = $color;
		$this->style             = $style;
		$this->numUsersCollected = $numUsersCollected;
		
		$this->imageURL       = $imageName === NULL? NULL : HOTWHEELS2_BASE_IMAGE_URL . $imageName . HOTWHEELS2_IMAGE_ICON_SUFFIX   . HOTWHEELS2_IMAGE_EXT;
		$this->detailImageURL = $imageName === NULL? NULL : HOTWHEELS2_BASE_IMAGE_URL . $imageName . HOTWHEELS2_IMAGE_DETAIL_SUFFIX . HOTWHEELS2_IMAGE_EXT;
		
		$this->owned = $owned;
	}
}
?>
