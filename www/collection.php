<?php
require_once __DIR__ . '/../utils/database.php';
require_once __DIR__ . '/../utils/authManager.php';
require_once __DIR__ . '/templates.php';

$db = new DB();
$cars = $db->getCollection(AuthManager::getLoggedInUser());
$db->close();

include __DIR__ . '/header.php';
echo '<br />';

if (count($cars))
{
	foreach ($cars as $car)
		Templates::carSearchResult($car);
	?>

<script type="text/javascript">
var __USER_ID =	"<?php echo AuthManager::getLoggedInUser(); ?>";

<?php include __DIR__ . '/js/toggleCarOwned.js'; ?>
</script>

	<?php
}

include __DIR__ . '/footer.php';
?>
