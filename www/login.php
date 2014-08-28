<?php
require_once __DIR__ . '/../utils/authManager.php';

$loginFailed = false;
if (isset($_POST['login']))
{
	if ($_POST['login'] === '111111')
	{
		AuthManager::login();
		
		header('Location: /');
		die();
	}
	else
		$loginFailed = true;
}

$NO_SEARCH_BAR = true;
include __DIR__ . '/header.php';

if ($loginFailed)
{
	echo '<span style="color: #CC0000;">Invalid Login! This is for Mike only!</span><br />';
	echo '</br />';
}
?>

<form method="post">
	<input type="text" name="login" /> <input type="submit" value="login" />
</form>

<?php
include __DIR__ . '/footer.php';
?>
