<?php
require 'includes/config.php';
require 'includes/hotWheelsAPI.php';
require 'includes/database.php';
require 'includes/templates.php';

include 'includes/header.php';

echo '<br />';

$db = new DB();
$carIDs = $db->getCarsOwned();
$db->close();

foreach ($carIDs as $carID)
{
	$result = HotWheelsAPI::getCarDetails($carID);
	
	if (is_string($result))
		echo '<div class="car-search-result">', $carID, ':<br />', $result, '</div>';
	else
	{
		$car = $result;
		
		$car->owned = true;
		Templates::carSearchResult($car);
	}
}
?>

<script type="text/javascript">
<?php include "/js/toggleCarOwned.js"; ?>
</script>

<?php
include 'includes/footer.html';
?>