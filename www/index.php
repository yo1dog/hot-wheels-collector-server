<?php
require 'includes/globals.php';
require '../config.php';

require 'includes/hotWheels2Models.php';
require 'includes/database.php';
require 'includes/templates.php';

$cars = NULL;

if (isset($_GET['query']))
{
	$query = $_GET['query'];
	
	$db = new DB();
	$cars = $db->search($query, $__USER_ID);
	$db->close();
}

include 'includes/header.php';

echo '<br />';

if ($cars !== NULL)
{
	foreach ($cars as $car)
		Templates::carSearchResult($car);
	?>

<script type="text/javascript">
var __USER_ID = "<?php echo $__USER_ID; ?>";

<?php include 'js/toggleCarOwned.js'; ?>
</script>

	<?php
}

include 'includes/footer.html';
?>

