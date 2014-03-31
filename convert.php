<?php
require 'config.php';
require 'www/includes/globals.php';
require 'www/includes/database.php';

$db = new DB();
$query = 'SELECT id, vehicle_id, name FROM cars';

$success = $db->mysqli->real_query($query);
if (!$success)
	throw new Exception('MySQL Error (' . $db->mysqli->errno . '): ' . $db->mysqli->error . "\n\nQuery:\n" . $query);

$result = $db->mysqli->store_result();
if ($result === false)
	throw new Exception('MySQL Error (' . $db->mysqli->errno . '): ' . $db->mysqli->error . "\n\nQuery:\n" . $query);

while (($row = $result->fetch_assoc()) !== NULL)
{
	$oldImageName = preg_replace('/[^a-zA-Z0-9]/', '_', $row['vehicle_id']);
	$newImageName = createCarImageName($row['id'], $row['name']);
	
	$oldBaseFilename   = HOTWHEELS2_IMAGE_PATH . $oldImageName . HOTWHEELS2_IMAGE_BASE_SUFFIX   . HOTWHEELS2_IMAGE_EXT;
	$oldIconFilename   = HOTWHEELS2_IMAGE_PATH . $oldImageName . HOTWHEELS2_IMAGE_ICON_SUFFIX   . HOTWHEELS2_IMAGE_EXT;
	$oldDetailFilename = HOTWHEELS2_IMAGE_PATH . $oldImageName . HOTWHEELS2_IMAGE_DETAIL_SUFFIX . HOTWHEELS2_IMAGE_EXT;
	
	$newBaseFilename   = HOTWHEELS2_IMAGE_PATH . $newImageName  . HOTWHEELS2_IMAGE_BASE_SUFFIX   . HOTWHEELS2_IMAGE_EXT;
	$newIconFilename   = HOTWHEELS2_IMAGE_PATH . $newImageName  . HOTWHEELS2_IMAGE_ICON_SUFFIX   . HOTWHEELS2_IMAGE_EXT;
	$newDetailFilename = HOTWHEELS2_IMAGE_PATH . $newImageName  . HOTWHEELS2_IMAGE_DETAIL_SUFFIX . HOTWHEELS2_IMAGE_EXT;
	
	rename($oldBaseFilename,   $newBaseFilename);
	rename($oldIconFilename,   $newIconFilename);
	rename($oldDetailFilename, $newDetailFilename);
	
	$db->setCarImageName($row['id'], $newImageName);
}

$db->close();
?>
