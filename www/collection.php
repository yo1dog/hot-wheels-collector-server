<?php
require 'includes/globals.php';
require '../config.php';

require 'includes/hotWheels2Models.php';
require 'includes/database.php';
require 'includes/templates.php';

$db = new DB();
$cars = $db->getCollection($__USER_ID);
$db->close();

include 'includes/header.php';

echo '<br />';

if (count($cars))
{
	foreach ($cars as $car)
		Templates::carSearchResult($car);
	?>

<script type="text/javascript">
var __USER_ID =	"<?php echo $__USER_ID; ?>";

<?php include "js/toggleCarOwned.js"; ?>
</script>

	<?php
}

include 'includes/footer.html';
?>
