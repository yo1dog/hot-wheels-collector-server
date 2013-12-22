<?php
class Templates
{
	public static function carSearchResult($car, $query = NULL)
	{
		$href = '/details.php?carID=' . urlencode($car->id);
		?>
			
<div class="car-search-result">
	<a href="<?php echo htmlspecialchars($href); ?>">
		<img class="car-img" src="<?php echo htmlspecialchars($car->imageURL); ?>" /><br />
		<span><?php echo htmlspecialchars($car->name); ?></span>
	</a>
	
	<a href="#" class="owned-banner search" onclick="return toggleCarOwned(this);" data-car-id="<?php echo htmlspecialchars($car->id); ?>" data-owned="<?php echo $car->owned? '1' : '0'; ?>">
		<img src="<?php echo $car->owned? "/img/ownedSmall.png" : "/img/unownedSmall.png"; ?>" />
	</a>
</div>

		<?php
	}
}
?>