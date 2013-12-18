<?php
require 'includes/globals.php';
require 'includes/hotWheelsAPI.php';
require '../config.php';
require 'includes/database.php';
require 'includes/templates.php';

include 'includes/header.php';

$result = NULL;
if ($query !== NULL)
	$result = HotWheelsAPI::search($query);

echo '<br />';

if ($result !== NULL)
{
	if (is_array($result))
	{
		$cars = $result;
		
		$db = new DB();
		$db->checkCarsOwned($cars);
		$db->close();
		
		foreach ($cars as $car)
			Templates::carSearchResult($car);
		?>
		
<script type="text/javascript">
<?php include 'js/toggleCarOwned.js'; ?>
</script>
		
		<?php
	}
	else
		echo '<div class="error">', htmlspecialchars($searchResults), '</div>';
}

include 'includes/footer.html';
?>