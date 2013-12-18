<?php
class DB
{
	private $mysqli;
	
	public function __construct()
	{
		$this->mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
		
		$errorNum = $this->mysqli->connect_errno;
		if ($errorNum !== 0)
			throw new Exception('Database connect error (' . $errorNum . '): ' . $this->mysqli->connect_error);
		
		if (!$this->mysqli->set_charset("utf8"))
			throw new Exception('Error loading character set utf8: ' . $this->mysqli->error);
	}
	
	
	public function search($query, $userID = NULL)
	{
		$query = 'SELECT *, ';
		
		if ($userID !== NULL)
			$query .= '(SELECT 1 FROM collections WHERE user_id = "' . $this->mysqli->real_escape_string($userID) . '" AND car_id = cars.id) AS owned';
		
		$query .= ' FROM cars WHERE name LIKE "%' . str_replace("%", "\\%", $this->mysqli->real_escape_string($query)) . '%" LIMIT ' . HOTWHEELS2_MAX_NUM_SEARCH_RESULTS;
		
		
		$success = $this->mysqli->real_query($query);
		if (!$success)
			throw new Exception("MySQL Error (" . $this->mysqli->errno . "): " . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$result = $this->mysqli->store_result();
		if ($result === false)
			throw new Exception("MySQL Error (" . $this->mysqli->errno . "): " . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		
		$cars = array();
		while (($row = $result->fetch_row()) !== NULL)
			$cars = new HW2Car($row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $userID !== NULL && $row[8] === '1');
		
		$result->close();
		
		return $cars;
	}
	
	public function getCollection($userID)
	{
		$query = 'SELECT cars.* FROM collections LEFT JOIN cars ON (cars.id = collections.car_id) WHERE user_id = "' . $this->mysqli->real_escape_string($userID) . '"';
		
		$success = $this->mysqli->real_query($query);
		if (!$success)
			throw new Exception("MySQL Error (" . $this->mysqli->errno . "): " . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$result = $this->mysqli->store_result();
		if ($result === false)
			throw new Exception("MySQL Error (" . $this->mysqli->errno . "): " . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$cars = array();
		while (($row = $result->fetch_row()) !== NULL)
			$cars = new HW2Car($row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], true);
		
		$result->close();
		
		return $cars;
	}
	
	public function setCarOwned($userID, $carID)
	{
		$query = 'INSERT INTO collections (user_id, car_id) VALUES ("' . $this->mysqli->real_escape_string($userID) . '", "' . $this->mysqli->real_escape_string(carID) . '")';
		
		$success = $this->mysqli->real_query($query);
		if (!$success)
			throw new Exception("MySQL Error (" . $this->mysqli->errno . "): " . $this->mysqli->error . "\n\nQuery:\n" . $query);
	}
	
	public function setCarUnowned($userID, $carID)
	{
		$query = 'DELETE FROM collections WHERE user_id = "' . $this->mysqli->real_escape_string($userID) . '" AND car_id = "' . $this->mysqli->real_escape_string($carID) . '"';
		
		$success = $this->mysqli->real_query($query);
		if (!$success)
		{
			$errorNum = $this->mysqli->errno;
			
			if ($errorNum !== 1062)
				throw new Exception("MySQL Error (" . $this->mysqli->errno . "): " . $this->mysqli->error . "\n\nQuery:\n" . $query);
		}
	}
	
	
	public function insertOrUpdateCar($id, $name, $toyNumber, $segment, $series, $carNumber, $color, $make)
	{
		$id        = $this->mysqli->real_escape_string($id);
		$name      = $this->mysqli->real_escape_string($name);
		$toyNumber = $this->mysqli->real_escape_string($toyNumber);
		$segment   = $this->mysqli->real_escape_string($segment);
		$series    = $this->mysqli->real_escape_string($series);
		$carNumber = $this->mysqli->real_escape_string($carNumber);
		$color     = $this->mysqli->real_escape_string($color);
		$make      = $this->mysqli->real_escape_string($make);
		
		$query = "SELECT 1 FROM cars WHERE id = \"$id\"";
		
		$success = $this->mysqli->real_query($query);
		if (!$success)
			throw new Exception("MySQL Error (" . $this->mysqli->errno . "): " . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$result = $this->mysqli->store_result();
		if ($result === false)
			throw new Exception("MySQL Error (" . $this->mysqli->errno . "): " . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		
		$row = $result->fetch_row();
		if ($row[0] === '1')
			$query = "UPDATE cars SET name = \"$name\", toy_number = \"$toyNumber\", segment = \"$segment\", series = \"$series\", car_number = \"$carNumber\", color = \"$color\", make = \"$make\" WHERE id = \"$id\"";
		else
			$query = "INSERT INTO cars (id, name, toy_number, segment, series, car_number, color, make) VALUES (\"$id\", \"$name\", \"$toyNumber\", \"$segment\", \"$series\", \"$carNumber\", \"$color\", \"$make\")";
		
		$success = $this->mysqli->real_query($query);
		if (!$success)
			throw new Exception("MySQL Error (" . $this->mysqli->errno . "): " . $this->mysqli->error . "\n\nQuery:\n" . $query);
	}
	
	
	public function close()
	{
		$this->mysqli->close();
	}
}
?>
