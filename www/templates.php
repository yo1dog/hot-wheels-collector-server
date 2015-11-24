<?php
require_once __DIR__ . '/../utils/hotWheelsCollectorCar.php';

class Templates
{
	public static function carSearchResult(HotWheelsCollectorCar $car, $query = NULL)
	{
		$href = '/details.php?carID=' . urlencode($car->id);
		?>
			
<div class="car-search-result">
	<div class="car-img-container">
		<span class="vert-center-spacer"></span>
		<!--<a href="<?php echo htmlspecialchars($href); ?>">-->
			<img class="car-img" src="<?php echo htmlspecialchars($car->iconImageURL); ?>" />
		<!--</a>-->
	</div>
	
	<div class="car-name-container">
		<a href="<?php echo htmlspecialchars($href); ?>">
			<?php echo htmlspecialchars($car->name); ?>
		</a>
	</div>
	
	<a href="#" class="owned-banner search" onclick="return toggleCarOwned(this);" data-car-id="<?php echo htmlspecialchars($car->id); ?>" data-owned="<?php echo $car->ownedTimestamp !== NULL? '1' : '0'; ?>">
		<img src="<?php echo $car->ownedTimestamp !== NULL? '/img/ownedSmall.png' : '/img/unownedSmall.png'; ?>" />
	</a>
</div>

		<?php
	}
}
?>
