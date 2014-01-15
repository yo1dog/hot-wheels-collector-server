<?php
class DB
{
	private $mysqli;
	
	public function __construct()
	{
		$this->mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
		
		$mysqlErrorNum = $this->mysqli->connect_errno;
		if ($mysqlErrorNum !== 0)
			throw new Exception('Database connect error (' . $mysqlErrorNum . '): ' . $this->mysqli->connect_error);
		
		if (!$this->mysqli->set_charset("utf8"))
			throw new Exception('Error loading character set utf8: ' . $this->mysqli->error);
	}
	
	
	public function search($searchQuery, $userID = NULL)
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
			
			$queryLike .= 'sort_name LIKE "%' . str_replace("%", "\\%", $this->mysqli->real_escape_string($term)) . '%"';
		}
		
		if ($queryLike === NULL)
			return NULL;

		$query = 'SELECT *';
		
		if ($userID !== NULL)
			$query .= ', (SELECT 1 FROM collections WHERE user_id = "' . $this->mysqli->real_escape_string($userID) . '" AND car_id = cars.id) AS owned';
		
		$query .= ' FROM cars WHERE ' . $queryLike . ' ORDER BY sort_name ASC LIMIT ' . HOTWHEELS2_MAX_NUM_SEARCH_RESULTS;
		
		
		
		$success = $this->mysqli->real_query($query);
		if (!$success)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$result = $this->mysqli->store_result();
		if ($result === false)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		
		$cars = array();
		while (($row = $result->fetch_row()) !== NULL)
			$cars[] = new HW2Car($row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8] === NULL ? NULL : intval($row[8]), $row[9], $row[10], $userID !== NULL && $row[11] === '1');
		
		$result->close();
		
		return $cars;
	}
	
	public function getCollection($userID)
	{
		$query = 'SELECT cars.* FROM collections LEFT JOIN cars ON (cars.id = collections.car_id) WHERE user_id = "' . $this->mysqli->real_escape_string($userID) . '" ORDER BY sort_name ASC';
		
		$success = $this->mysqli->real_query($query);
		if (!$success)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$result = $this->mysqli->store_result();
		if ($result === false)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$cars = array();
		while (($row = $result->fetch_row()) !== NULL)
			$cars[] = new HW2Car($row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8] === NULL ? NULL : intval($row[8]), $row[9], $row[10], true);
		
		$result->close();
		
		return $cars;
	}
	
	public function getCar($carID, $userID)
	{
		$query = 'SELECT *';
		
		if ($userID !== NULL)
			$query .= ', (SELECT 1 FROM collections WHERE user_id = "' . $this->mysqli->real_escape_string($userID) . '" AND car_id = cars.id) AS owned';
		
		$query .= ' FROM cars WHERE id = "' . $this->mysqli->real_escape_string($carID) . '"';
		
		$success = $this->mysqli->real_query($query);
		if (!$success)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$result = $this->mysqli->store_result();
		if ($result === false)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$car = NULL;
		$row = $result->fetch_row();
		
		if ($row)
			$car = new HW2Car($row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8] === NULL ? NULL : intval($row[8]), $row[9], $row[10], $userID !== NULL && $row[11] === '1');
		
		$result->close();
		
		return $car;	
	}
	
	public function getCarByToyNumber($toyNumber, $userID)
	{
		$query = 'SELECT *';
		
		if ($userID !== NULL)
			$query .= ', (SELECT 1 FROM collections WHERE user_id = "' . $this->mysqli->real_escape_string($userID) . '" AND car_id = cars.id) AS owned';
		
		$query .= ' FROM cars WHERE toy_number = "' . $this->mysqli->real_escape_string($toyNumber) . '"';
				
		$success = $this->mysqli->real_query($query);
		if (!$success)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$result = $this->mysqli->store_result();
		if ($result === false)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$car = NULL;
		$row = $result->fetch_row();
		
		if ($row)
			$car = new HW2Car($row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8] === NULL ? NULL : intval($row[8]), $row[9], $row[10], $userID !== NULL && $row[11] === '1');
		
		$result->close();
		
		return $car;	
	}
	
	public function getMostCollectedCars($userID = NULL)
	{
		$query = 'SELECT *';
		
		if ($userID !== NULL)
			$query .= ', (SELECT 1 FROM collections WHERE user_id = "' . $this->mysqli->real_escape_string($userID) . '" AND car_id = cars.id) AS owned';
		
		$query .= ' FROM cars ORDER BY num_users_collected DESC LIMIT ' . HOTWHEELS2_MAX_NUM_MOST_COLLECTED;
		
		$success = $this->mysqli->real_query($query);
		if (!$success)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$result = $this->mysqli->store_result();
		if ($result === false)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		
		$cars = array();
		while (($row = $result->fetch_row()) !== NULL)
			$cars[] = new HW2Car($row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8] === NULL ? NULL : intval($row[8]), $row[9], $row[10], $userID !== NULL && $row[11] === '1');
		
		$result->close();
		
		return $cars;
	}
	
	public function getCollectionRemovals($userID)
	{
		$query = '
		SELECT
			cars.*,
			(
				SELECT 1
				FROM collections
				WHERE user_id = "' . $this->mysqli->real_escape_string($userID) . '" AND car_id = cars.id
			) AS owned
		FROM collection_removals
		LEFT JOIN cars ON (cars.id = collection_removals.car_id)
		WHERE user_id = "' . $this->mysqli->real_escape_string($userID) . '" ORDER BY timestamp DESC LIMIT ' . HOTWHEELS2_MAX_NUM_REMOVALS;
		
		$success = $this->mysqli->real_query($query);
		if (!$success)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$result = $this->mysqli->store_result();
		if ($result === false)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$cars = array();
		while (($row = $result->fetch_row()) !== NULL)
			$cars[] = new HW2Car($row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8] === NULL ? NULL : intval($row[8]), $row[9], $row[10], $row[11] === '1');
		
		$result->close();
		
		return $cars;
	}
	
	
	
	
	public function setCarOwned($userID, $carID)
	{
		$query = 'INSERT INTO collections (user_id, car_id) VALUES ("' . $this->mysqli->real_escape_string($userID) . '", "' . $this->mysqli->real_escape_string($carID) . '")';
		
		$success = $this->mysqli->real_query($query);
		if (!$success)
		{
			$mysqlErrorNum = $this->mysqli->errno;
			
			// don't worry about duplicate unique keys. this just means the user already owns the car
			if ($mysqlErrorNum !== 1062)
				throw new Exception('MySQL Error (' . $mysqlErrorNum . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		}
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
					$query = 'SELECT timestamp FROM collection_removals WHERE user_id = "' . $this->mysqli->real_escape_string($userID) . '" ORDER BY timestamp DESC LIMIT ' . HOTWHEELS2_MAX_NUM_REMOVALS . ', 1';
					
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
	
	
	public function insertOrUpdateCar($id, $name, $toyNumber, $segment, $series, $carNumber, $color, $make, $numUsersCollected, $imageName, $sortName)
	{
		$id                = $this->mysqli->real_escape_string($id);
		$name              = $this->mysqli->real_escape_string($name);
		$toyNumber         = $this->mysqli->real_escape_string($toyNumber);
		$segment           = $this->mysqli->real_escape_string($segment);
		$series            = $this->mysqli->real_escape_string($series);
		$carNumber         = $this->mysqli->real_escape_string($carNumber);
		$color             = $this->mysqli->real_escape_string($color);
		$make              = $this->mysqli->real_escape_string($make);
		$numUsersCollected = $numUsersCollected === NULL ? 'NULL' : '"' . $this->mysqli->real_escape_string($numUsersCollected) . '"';
		$imageName         = $this->mysqli->real_escape_string($imageName);
		$sortName          = $this->mysqli->real_escape_string($sortName);
		
		$query = "SELECT 1 FROM cars WHERE id = \"$id\"";
		
		$success = $this->mysqli->real_query($query);
		if (!$success)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$result = $this->mysqli->store_result();
		if ($result === false)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		
		$row = $result->fetch_row();
		if ($row[0] === '1')
			$query = "UPDATE cars SET name = \"$name\", toy_number = \"$toyNumber\", segment = \"$segment\", series = \"$series\", car_number = \"$carNumber\", color = \"$color\", make = \"$make\", num_users_collected = $numUsersCollected, image_name = \"$imageName\", sort_name = \"$sortName\" WHERE id = \"$id\"";
		else
			$query = "INSERT INTO cars (id, name, toy_number, segment, series, car_number, color, make, num_users_collected, image_name, sort_name) VALUES (\"$id\", \"$name\", \"$toyNumber\", \"$segment\", \"$series\", \"$carNumber\", \"$color\", \"$make\", $numUsersCollected, \"$imageName\", \"$sortName\")";
		
		$success = $this->mysqli->real_query($query);
		if (!$success)
			throw new Exception('MySQL Error (' . $this->mysqli->errno . '): ' . $this->mysqli->error . "\n\nQuery:\n" . $query);
	}
	
	
	public function close()
	{
		$this->mysqli->close();
	}
}
?>
