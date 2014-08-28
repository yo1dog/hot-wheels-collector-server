<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<title>HotWheels 2.0</title>
		<link rel="stylesheet" href="/css/style.css" />
		
		<meta name="viewport" content="initial-scale=0.5">
	</head>
	<body>
	
<?php
if (isset($NO_SEARCH_BAR) && $NO_SEARCH_BAR === true)
	return;

if (AuthManager::getLoggedInUser() === '2') 
	echo '<span>Browsing as a <strong>Guest</strong>. Feel free to make changes and play around. <a href="/login.php">Login</a></span><br />';
else
	echo '<span>Hello <strong>Mike (' . AuthManager::getLoggedInUser() . ')</strong> - <a href="/logout.php">Logout</a></span><br />';
?>
	<br />
	
	<form action="/" method="get" class="search-bar">
		<table>
			<tbody>
				<tr>
					<td style="width: 100%; padding-left: 5px;"><input type="text" name="query" style="width: 100%;" value="<?php if (isset($_GET['query'])) echo htmlspecialchars($_GET['query']);  ?>" /></td>
					<td style="padding-left: 20px;"><input type="submit" class="button" value="Search" /></td>
					<td style="padding-left: 5px;"><input type="button" class="button" value="Collection" onclick="window.location='/collection.php';" /></td>
				</tr>
			</tbody>
		</table>
	</form>
