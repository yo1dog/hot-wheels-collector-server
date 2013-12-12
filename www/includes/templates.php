<?php
class Templates
{
	public static function carSearchResult($car, $query = NULL)
	{
		$href = '/details.php?carID=' . urlencode($car->id);
		?>
			
<div class="car-search-result">
	<a href="<?php echo htmlspecialchars($href); ?>">
		<img src="<?php echo htmlspecialchars($car->imageURL); ?>" /><br />
		<span><?php echo htmlspecialchars($car->name); ?></span>
	</a>
	
	<a href="#" class="owned-banner search" onclick="return toggleCarOwned(this);" data-car-id="<?php echo htmlspecialchars($car->id); ?>" data-owned="<?php echo $car->owned? '1' : '0'; ?>">
		<img src="<?php echo $car->owned? "/img/owned.png" : "/img/unowned.png"; ?>" />
	</a>
</div>

		<?php
	}
}
?>