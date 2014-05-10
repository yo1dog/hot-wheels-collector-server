<?php
include __DIR__ . '/header.php';
?>

<br />

<form method="POST" action="/api/addCustomCar.php?userID=1&addToCollection=0" enctype="multipart/form-data">
	<table>
		<tr>
			<th><label for="name">Name:</label></th>
			<td><input type="text" name="name" /></td>
		</tr>
		<tr>
			<th><label for="segment">Segment:</label></th>
			<td><input type="text" name="segment" /></td>
		</tr>
		<tr>
			<th><label for="series">Series:</label></th>
			<td><input type="text" name="series" /></td>
		</tr>
		<tr>
			<th><label for="make">Make:</label></th>
			<td><input type="text" name="make" /></td>
		</tr>
		<tr>
			<th><label for="color">Color:</label></th>
			<td><input type="text" name="color" /></td>
		</tr>
		<tr>
			<th><label for="style">Style:</label></th>
			<td><input type="text" name="style" /></td>
		</tr>
		<tr>
			<th><label for="customToyNumber">Toy Number:</label></th>
			<td><input type="text" name="customToyNumber" /></td>
		</tr>
		<tr>
			<th><label for="customToyNumber">Barcode Data:</label></th>
			<td><input type="text" name="barcodeData" /></td>
		</tr>
	</table>
	
	<br />
	<label for="distinguishingNotes">Distinguishing Notes:</label><br />
	<textarea name="distinguishingNotes"></textarea><br />
	
	<br />
	<label for="carPicture">Car Picture:</label><br />
	<input type="file" name="carPicture" /><br />
	
	<br />
	<input type="submit" />
</form>
	

<?php
include __DIR__ . '/footer.php';
?>