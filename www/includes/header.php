<?php
session_start();

$query = NULL;
if (isset($_GET['query']))
{
	$query = $_GET['query'];
	$_SESSION['query'] = $query;
}
else if (isset($_SESSION['query']))
	$query = $_SESSION['query'];
?>

<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<title>HotWheels 2.0</title>
		<link rel="stylesheet" href="/css/style.css" />
		
		<meta name="viewport" content="initial-scale=0.5">
	</head>
	<body>
	
	<form action="/" method="get" class="search-bar">
		<table>
			<tbody>
				<tr>
					<td style="width: 100%; padding-left: 5px;"><input type="text" name="query" value="<?php if ($query !== NULL) echo htmlspecialchars($query) ?>" style="width: 100%;" /></td>
					<td style="padding-left: 20px;"><input type="submit" class="button" value="Search" /></td>
					<td style="padding-left: 5px;"><input type="button" class="button" value="Collection" onclick="window.location='/collection.php';" /></td>
				</tr>
			</tbody>
		</table>
	</form>