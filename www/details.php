<?php
if (!isset($_GET['carID']))
{
	http_response_code(400);
	die('"carID" missing from query string.');
}

require 'includes/config.php';
require 'includes/hotWheelsAPI.php';
require 'includes/database.php';

include 'includes/header.php';

$carID = $_GET['carID'];
$result = HotWheelsAPI::getCarDetails($carID);

if (is_object($result))
{
	$car = $result;
	
	$db = new DB();
	$db->checkCarsOwned(array($car));
	$db->close();
	?>

<h1><?php echo $car->name; ?></h1>

<div class="detail-image-container">
	<img src="<?php echo htmlspecialchars($car->detailImageURL); ?>" />
	
	<a href="#" class="owned-banner details" onclick="return toggleCarOwned(this);" data-car-id="<?php echo htmlspecialchars($car->id); ?>" data-owned="<?php echo $car->owned? '1' : '0'; ?>">
		<img src="<?php echo $car->owned? "/img/owned.png" : "/img/unowned.png"; ?>" />
	</a>
</div>

<table class="detail-table">
	<tbody>
		<tr>
			<th>Segment:</th>
			<td><?php echo htmlspecialchars($car->segment); ?></td>
		</tr>
		<tr>
			<th>Series:</th>
			<td><?php echo htmlspecialchars($car->series); ?></td>
		</tr>
		<tr>
			<th>Car Number:</th>
			<td><?php echo htmlspecialchars($car->carNumber); ?></td>
		</tr>
		<tr>
			<th>Color:</th>
			<td><?php echo htmlspecialchars($car->color); ?></td>
		</tr>
		<tr>
			<th>Make:</th>
			<td><?php echo htmlspecialchars($car->make); ?></td>
		</tr>
		<tr>
			<th>Toy Number:</th>
			<td><?php echo htmlspecialchars($car->toyNumber); ?></td>
		</tr>
	</tbody>
</table>

<script type="text/javascript">
<?php include "/js/toggleCarOwned.js"; ?>
</script>

	<?php
}
else
	echo '<br /><div class="error">', htmlspecialchars($result), '</div>';

include 'includes/footer.html';
?>