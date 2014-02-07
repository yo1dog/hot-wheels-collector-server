<?php
if (!isset($_GET['carID']))
{
	http_response_code(400);
	die('"carID" missing from query string.');
}

require 'includes/globals.php';
require '../config.php';

require 'includes/hotWheels2Models.php';
require 'includes/database.php';

$carID = $_GET['carID'];

$db = new DB();
$car = $db->getCar($carID, $__USER_ID);
$db->close();

if ($car === NULL)
{
	http_response_code(404);
	die('carID "' . $carID . '" not found.');
}

include 'includes/header.php';
?>

<h1><?php echo $car->name; ?></h1>

<div class="detail-image-container">
	<img src="<?php echo htmlspecialchars($car->detailImageURL); ?>" class="detail-car-img" />
	
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
			<th>Make:</th>
			<td><?php echo htmlspecialchars($car->make); ?></td>
		</tr>
		<tr>
			<th>Color:</th>
			<td><?php echo htmlspecialchars($car->color); ?></td>
		</tr>
		<tr>
			<th>Style:</th>
			<td><?php echo htmlspecialchars($car->style); ?></td>
		</tr>
		<tr>
			<th>Toy Number:</th>
			<td><?php echo htmlspecialchars($car->toyNumber); ?></td>
		</tr>
	</tbody>
</table>

<script type="text/javascript">
var __USER_ID =	"<?php echo $__USER_ID; ?>";

<?php include 'js/toggleCarOwned.js'; ?>
</script>

<?php
include 'includes/footer.html';
?>
