<?php
require_once __DIR__ . '/../utils/database.php';
require_once __DIR__ . '/../utils/authManager.php';
require_once __DIR__ . '/templates.php';

$cars = NULL;

if (isset($_GET['query']))
{
	$query = $_GET['query'];
	
	$db = new DB();
	$cars = $db->search($query, AuthManager::getLoggedInUser());
	$db->close();
}

include __DIR__ . '/header.php';
echo '<br />';

if ($cars !== NULL)
{
	foreach ($cars as $car)
		Templates::carSearchResult($car);
	?>

<script type="text/javascript">
var __USER_ID = "<?php echo AuthManager::getLoggedInUser(); ?>";

<?php include __DIR__ . '/js/toggleCarOwned.js'; ?>
</script>

	<?php
}

include __DIR__ . '/footer.php';
?>

