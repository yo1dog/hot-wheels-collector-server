<?php
class HW2Car
{
	public $id;
	public $name;
	public $toyNumber;
	public $segment;
	public $series;
	public $carNumber;
	public $color;
	public $make;
	public $numUsersCollected;
	
	public $owned;
	
	public $imageURL;
	public $detailImageURL;
	
	public function __construct($id, $name, $toyNumber, $segment, $series, $carNumber, $color, $make, $numUsersCollected, $imageName, $sortName, $owned)
	{
		$this->id                = $id;
		$this->name              = $name;
		$this->toyNumber         = $toyNumber;
		$this->segment           = $segment;
		$this->series            = $series;
		$this->carNumber         = $carNumber;
		$this->color             = $color;
		$this->make              = $make;
		$this->numUsersCollected = $numUsersCollected;
		
		$this->owned = $owned;
		
		$this->imageURL       = HOTWHEELS2_BASE_IMAGE_URL . $imageName . HOTWHEELS2_IMAGE_EXT;
		$this->detailImageURL = HOTWHEELS2_BASE_IMAGE_URL . $imageName . HOTWHEELS2_IMAGE_DETAIL_SUFFIX . HOTWHEELS2_IMAGE_EXT;
	}
}
?>
