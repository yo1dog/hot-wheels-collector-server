<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/hotWheelsCollectorCar.php';

class DB
{
	public $mysqli;
	
	public function __construct()
	{
		$this->mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
		
		$mysqlErrorNum = $this->mysqli->connect_errno;
		if ($mysqlErrorNum !== 0)
			throw new Exception('Database connect error (' . $mysqlErrorNum . '): ' . $this->mysqli->connect_error);
		
		if (!$this->mysqli->set_charset('utf8'))
			throw new Exception('Error loading character set utf8: ' . $this->mysqli->error);
	}
	
	public function search($searchQuery, $userID = NULL, $page = 0, &$numPages = NULL)
	{
		$queryLike = NULL;
		$terms = explode(' ', strtolower($searchQuery));
		
		foreach ($terms as $term)
		{
			if (strlen($term) === 0)
				continue;
			
			if ($queryLike === NULL)
				$queryLike = '';
			else
				$queryLike .= ' AND ';
			
			$queryLike .= 'cars.sort_name LIKE "%' . (string)str_replace("%", "\\%", $this->mysqli->real_escape_string($term)) . '%"';
		}
		
		if ($queryLike === NULL)
			return NULL;
		
		$query = 'SELECT cars.*, ' . ($userID === NULL? 'NULL' : 'collections.timestamp') . ' AS ownedTimestamp FROM cars';
		
		if ($userID !== NULL)
			$query .= ' LEFT JOIN collections ON (collections.user_id = "' . (string)$this->mysqli->real_escape_string($userID) . '" AND collections.car_id = cars.id)';
		
		$query .= ' WHERE ' . $queryLike . ' ORDER BY sort_name ASC LIMIT ' . ($page * RESULTS_PER_PAGE) . ', ' . RESULTS_PER_PAGE;
		
		$success = $this->mysqli->real_query($query);
		if (!$success)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$result = $this->mysqli->store_result();
		if ($result === false)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		
		$cars = array();
		while (($row = $result->fetch_assoc()) !== NULL)
			$cars[] = new HotWheelsCollectorCar($row);
		
		$result->close();
		
		
		// get the number of pages
		$query = 'SELECT COUNT(*) FROM cars WHERE ' . $queryLike;		
		$success = $this->mysqli->real_query($query);
		if (!$success)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$result = $this->mysqli->store_result();
		if ($result === false)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$row = $result->fetch_row();
		
		if ($row === NULL)
			throw new Exception("Error getting number of cars.");
		
		$numCars = intval($row[0]);
		$numPages = ceil($numCars / RESULTS_PER_PAGE);
		if ($numPages < 1)
			$numPages = 1;
		
		$result->close();
		return $cars;
	}
	
	public function getCollection($userID)//, $page = 0, &$numPages = NULL)
	{
		$queryWhere = 'user_id = "' . $this->mysqli->real_escape_string($userID) . '"';
		$query = 'SELECT cars.*, collections.timestamp AS ownedTimestamp FROM collections LEFT JOIN cars ON (cars.id = collections.car_id) WHERE ' . $queryWhere . ' ORDER BY sort_name ASC';// LIMIT ' . ($page * RESULTS_PER_PAGE) . ', ' . RESULTS_PER_PAGE;
		
		$success = $this->mysqli->real_query($query);
		if (!$success)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$result = $this->mysqli->store_result();
		if ($result === false)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$cars = array();
		while (($row = $result->fetch_assoc()) !== NULL)
			$cars[] = new HotWheelsCollectorCar($row);
		
		$result->close();
		
		// get the number of pages
		/*$query = 'SELECT COUNT(*) FROM collections WHERE ' . $queryWhere;
		$success = $this->mysqli->real_query($query);
		if (!$success)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$result = $this->mysqli->store_result();
		if ($result === false)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		if ($row === NULL)
			throw new Exception("Error getting number of cars.");
		
		$numCars = intval($row[0]);
		$numPages = ceil($numCars / RESULTS_PER_PAGE);
		if ($numPages < 1)
			$numPages = 1;
		
		$result->close();*/
		
		return $cars;
	}
	
	public function getCar($carID, $userID)
	{
		$query = 'SELECT cars.*, ' . ($userID === NULL? 'NULL' : 'collections.timestamp') . ' AS ownedTimestamp FROM cars';
		
		if ($userID !== NULL)
			$query .= ' LEFT JOIN collections ON (collections.user_id = "' . $this->mysqli->real_escape_string($userID) . '" AND collections.car_id = cars.id)';
		
		$query .= ' WHERE id = "' . $this->mysqli->real_escape_string($carID) . '"';
		
		$success = $this->mysqli->real_query($query);
		if (!$success)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$result = $this->mysqli->store_result();
		if ($result === false)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$car = NULL;
		$row = $result->fetch_assoc();
		
		if ($row)
			$car = new HotWheelsCollectorCar($row);
		
		$result->close();
		
		return $car;
	}
	
	public function getCarByToyNumber($toyNumber, $userID)
	{
		$query = 'SELECT cars.*, ' . ($userID === NULL? 'NULL' : 'collections.timestamp') . ' AS ownedTimestamp FROM cars';
		
		if ($userID !== NULL)
			$query .= ' LEFT JOIN collections ON (collections.user_id = "' . $this->mysqli->real_escape_string($userID) . '" AND collections.car_id = cars.id)';
		
		$query .= ' WHERE toy_number = "' . $this->mysqli->real_escape_string($toyNumber) . '"';
				
		$success = $this->mysqli->real_query($query);
		if (!$success)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$result = $this->mysqli->store_result();
		if ($result === false)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$car = NULL;
		$row = $result->fetch_assoc();
		
		if ($row)
			$car = new HotWheelsCollectorCar($row);
		
		$result->close();
		
		return $car;	
	}
	
	public function getMostCollectedCars($userID = NULL)
	{
		$query = 'SELECT cars.*, ' . ($userID === NULL? 'NULL' : 'collections.timestamp') . ' AS ownedTimestamp FROM cars';
		
		if ($userID !== NULL)
			$query .= ' LEFT JOIN collections ON (collections.user_id = "' . $this->mysqli->real_escape_string($userID) . '" AND collections.car_id = cars.id)';
		
		$query .= ' ORDER BY num_users_collected DESC LIMIT ' . MAX_NUM_MOST_COLLECTED_RESULTS;
		
		$success = $this->mysqli->real_query($query);
		if (!$success)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$result = $this->mysqli->store_result();
		if ($result === false)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		
		$cars = array();
		while (($row = $result->fetch_assoc()) !== NULL)
			$cars[] = new HotWheelsCollectorCar($row);
		
		$result->close();
		
		return $cars;
	}
	
	public function getCollectionRemovals($userID)
	{
		$userID = $this->mysqli->real_escape_string($userID);
		
		$query = '
		SELECT cars.*, collections.timestamp AS ownedTimestamp
		FROM collection_removals
		LEFT JOIN cars ON (cars.id = collection_removals.car_id)
		LEFT JOIN collections ON (collections.user_id = "' . $userID . '" AND collections.car_id = cars.id)
		WHERE collection_removals.user_id = "' . $userID . '"
		ORDER BY collection_removals.timestamp DESC
		LIMIT ' . MAX_NUM_COLLECTION_REMOVALS_RESULTS;
		
		$success = $this->mysqli->real_query($query);
		if (!$success)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$result = $this->mysqli->store_result();
		if ($result === false)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$cars = array();
		while (($row = $result->fetch_assoc()) !== NULL)
			$cars[] = new HotWheelsCollectorCar($row);
		
		$result->close();
		
		return $cars;
	}
	
	
	
	
	public function setCarOwned($userID, $carID, &$newTimestamp = NULL, &$alreadyOwned = NULL)
	{
		// check if already owned
		$query = 'SELECT timestamp FROM collections WHERE user_id = "' . $this->mysqli->real_escape_string($userID) . '" AND car_id = "' . $this->mysqli->real_escape_string($carID) . '"';
		$success = $this->mysqli->real_query($query);
		if (!$success)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$result = $this->mysqli->store_result();
		if ($result === false)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$row = $result->fetch_row();
		$result->close();
		
		if ($row)
		{
			$newTimestamp = intval($row[0]);
			$alreadyOwned = true;
			
			return true;
		}
		
		$alreadyOwned = false;
		
		// add the car to collection
		$timestamp = time();
		$query = 'INSERT INTO collections (user_id, car_id, timestamp) VALUES ("' . $this->mysqli->real_escape_string($userID) . '", "' . $this->mysqli->real_escape_string($carID) . '", ' . $timestamp . ')';
		
		$success = $this->mysqli->real_query($query);
		if (!$success)
		{
			$mysqlErrorNum = $this->mysqli->errno;
			
			// car or user does not exist
			if ($mysqlErrorNum === 1452)
				return false;
			
			throw new Exception('MySQL Error (' . $mysqlErrorNum . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		}
		
		$newTimestamp = $timestamp;
		return true;
	}
	
	public function setCarUnowned($userID, $carID)
	{
		$query = 'DELETE FROM collections WHERE user_id = "' . $this->mysqli->real_escape_string($userID) . '" AND car_id = "' . $this->mysqli->real_escape_string($carID) . '"';
		
		$success = $this->mysqli->real_query($query);
		if (!$success)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		// make sure we actually deleted something
		if ($this->mysqli->affected_rows > 0)
		{
			try
			{
				// insert the collection removal
				$query = 'INSERT INTO collection_removals (user_id, car_id) VALUES ("' . $this->mysqli->real_escape_string($userID) . '", "' . $this->mysqli->real_escape_string($carID) . '")';
				
				$success = $this->mysqli->real_query($query);
				if ($success)
				{
					// remove the last collection removal if we hit our max
					$query = 'SELECT timestamp FROM collection_removals WHERE user_id = "' . $this->mysqli->real_escape_string($userID) . '" ORDER BY timestamp DESC LIMIT ' . MAX_NUM_COLLECTION_REMOVALS_RESULTS . ', 1';
					
					$success = $this->mysqli->real_query($query);
					if (!$success)
						throw new Exception('Failed to get oldest collecion removal. MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
					
					$result = $this->mysqli->store_result();
					$row = $result->fetch_row();
					$result->close();
					
					if ($row !== NULL)
					{
						// we have reached our limit, remove this and all older collection removals
						$query = 'DELETE FROM collection_removals WHERE user_id = "' . $this->mysqli->real_escape_string($userID) . '" AND timestamp <= "' . $row[0] . '"';
						
						$success = $this->mysqli->real_query($query);
						if (!$success)
							throw new Exception('Failed to remove old collecion removals. MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
					}
				}
				else
				{
					$mysqlErrorNum = $this->mysqli->errno;
					
					// check if that there was already a colelction removal for that user and car
					if ($mysqlErrorNum !== 1062)
						throw new Exception('Failed to insert collection removal. MySQL Error (' . $mysqlErrorNum . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
					
					// update the timestamp
					$query = 'UPDATE collection_removals SET timestamp = NOW() WHERE user_id = "' . $this->mysqli->real_escape_string($userID) . '" AND car_id = "' . $this->mysqli->real_escape_string($carID) . '"';
					
					$success = $this->mysqli->real_query($query);
					if (!$success)
						throw new Exception('Failed to update collecion removal timestamp. MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
				}
			}
			catch(Exception $e)
			{
				error_log($e->getMessage());
			}
		}
	}
	
	public function insertOrUpdateCar($car, &$added = NULL, &$updated = NULL, &$updatedFields = NULL)
	{
		$vehicleID         = $this->mysqli->real_escape_string($car->vehicleID);
		$name              = $this->mysqli->real_escape_string($car->name);
		$toyNumber         = $this->mysqli->real_escape_string($car->toyNumber);
		$segment           = $this->mysqli->real_escape_string($car->segment);
		$series            = $car->series === NULL ? 'NULL' : '"' . $this->mysqli->real_escape_string($car->series) . '"';
		$make              = $this->mysqli->real_escape_string($car->make);
		$color             = $this->mysqli->real_escape_string($car->color);
		$carNum            = $car->carNum === NULL ? 'NULL' : '"' . $this->mysqli->real_escape_string($car->carNum) . '"';
		$numUsersCollected = $car->numUsersCollected === NULL ? 'NULL' : '"' . $this->mysqli->real_escape_string($car->numUsersCollected) . '"';
		$sortName          = $this->mysqli->real_escape_string($car->sortName);
		
		// check if the vehicle ID exists
		$query = "SELECT * FROM cars WHERE vehicle_id = \"$vehicleID\"";
		
		$success = $this->mysqli->real_query($query);
		if (!$success)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$result = $this->mysqli->store_result();
		if ($result === false)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$row = $result->fetch_assoc();
		$existingCar = $row === NULL ? NULL : $row;
		
		if ($existingCar === NULL)
		{
			// check if the vehicle ID has changed
			// see if there is a car with the same name, toy number, segment, and make
			$query = "SELECT * FROM cars WHERE name = \"$name\" AND toy_number = \"$toyNumber\" AND segment=\"$segment\" AND make=\"$make\"";
			
			$success = $this->mysqli->real_query($query);
			if (!$success)
				throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
			
			$result = $this->mysqli->store_result();
			if ($result === false)
				throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
			
			$row = $result->fetch_assoc();
			
			// if there is, use it
			if ($row !== NULL)
				$existingCar = $row;
		}
		
		if ($existingCar !== NULL)
		{
			$query = "UPDATE cars SET vehicle_id = \"$vehicleID\", name = \"$name\", toy_number = \"$toyNumber\", segment = \"$segment\", series = $series, make = \"$make\", color = \"$color\", car_num = $carNum, num_users_collected = $numUsersCollected, sort_name = \"$sortName\" WHERE id = \"" . $this->mysqli->real_escape_string($existingCar['id']) . "\"";
			
			$success = $this->mysqli->real_query($query);
			if (!$success)
				throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
			
			$added = false;
			$updated = $this->mysqli->affected_rows > 0;
			
			$existingCar = new HotWheelsCollectorCar($existingCar);
			$updatedFields = $existingCar->diff($car);
			
			return $existingCar->id;
		}
		else
		{
			$query = "INSERT INTO cars (vehicle_id, name, toy_number, segment, series, make, color, car_num, num_users_collected, sort_name) VALUES (\"$vehicleID\", \"$name\", \"$toyNumber\", \"$segment\", $series, \"$make\", \"$color\", $carNum, $numUsersCollected, \"$sortName\")";
			
			$success = $this->mysqli->real_query($query);
			if (!$success)
				throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
			
			$newCarID = $this->mysqli->insert_id;
			
			if ($newCarID === 0)
				throw new Exception('Unable to get last inserted ID. MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->errors);
			
			$added = true;
			$updated = false;
			$updatedFields = NULL;
			
			return $newCarID;
		}
	}
	
	public function getCarImageName($carID)
	{
		$carID = $this->mysqli->real_escape_string($carID);
		$query = "SELECT image_name FROM cars WHERE id=\"$carID\"";
		
		$success = $this->mysqli->real_query($query);
		if (!$success)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$result = $this->mysqli->store_result();
		if ($result === false)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$row = $result->fetch_row();
		
		if ($row === NULL)
			throw new Exception("Error getting image name: No car found with ID \"$carID\".");
		
		return $row[0];
	}
	
	public function setCarImageName($carID, $imageName)
	{
		$carID     = $this->mysqli->real_escape_string($carID);
		$imageName = $this->mysqli->real_escape_string($imageName);
		
		$query = "UPDATE cars SET image_name = \"$imageName\" WHERE id = \"$carID\"";
		
		$success = $this->mysqli->real_query($query);
		if (!$success)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
	}
	
	
	public function insertCustomCar($creatorUserID, $name, $segment, $series, $make, $color, $carNum, $sortName, $customToyNumber, $distinguishingNotes, $barcodeData)
	{
		$creatorUserID       = $this->mysqli->real_escape_string($creatorUserID);
		$name                = $this->mysqli->real_escape_string($name);
		$segment             = $this->mysqli->real_escape_string($segment);
		$series              = $this->mysqli->real_escape_string($series);
		$make                = $this->mysqli->real_escape_string($make);
		$color               = $this->mysqli->real_escape_string($color);
		$carNum              = $this->mysqli->real_escape_string($carNum);
		$sortName            = $this->mysqli->real_escape_string($sortName);
		$customToyNumber     = $customToyNumber     === NULL? 'NULL' : '"' . $this->mysqli->real_escape_string($customToyNumber)     . '"';
		$distinguishingNotes = $distinguishingNotes === NULL? 'NULL' : '"' . $this->mysqli->real_escape_string($distinguishingNotes) . '"';
		$barcodeData         = $barcodeData         === NULL? 'NULL' : '"' . $this->mysqli->real_escape_string($barcodeData)         . '"';
		
		$query = "INSERT INTO cars (name, segment, series, make, color, car_num, sort_name, is_custom, custom_toy_number, distinguishing_notes, barcode_data) VALUES (\"$name\", \"$segment\", $series, \"$make\", \"$color\", $carNum, \"$sortName\", 1, $customToyNumber, $distinguishingNotes, $barcodeData)";
		
		$success = $this->mysqli->real_query($query);
		if (!$success)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$newCarID = $this->mysqli->insert_id;
		
		if ($newCarID === 0)
			throw new Exception('Unable to get last inserted id. MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->errors);
		
		return $newCarID;
	}
	
	public function removeCar($carID)
	{
		$carID = $this->mysqli->real_escape_string($carID);
		$query = "DELETE FROM cars WHERE id = \"$carID\"";
		
		$success = $this->mysqli->real_query($query);
		if (!$success)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		if ($this->mysqli->affected_rows === 0)
			throw new Exception("Error removing car: No cars affected with ID \"$carID\".");
	}
	
	
	public function close()
	{
		$this->mysqli->close();
	}
}
?>
