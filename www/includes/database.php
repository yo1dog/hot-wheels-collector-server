<?php
require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

class DB
{
	private $mysqli;
	
	public function __construct()
	{
		$this->mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
		
		$errorNum = $this->mysqli->connect_errno;
		if ($errorNum !== 0)
			throw new Exception('Database connect error (' . $errorNum . '): ' . $this->mysqli->connect_error);
	}
	
	public function checkCarsOwned($cars)
	{
		if (count($cars) === 0)
			return;
		
		$query = NULL;
		
		foreach ($cars as $car)
		{
			if ($query === NULL)
				$query = '';
			else
				$query .= ",\n";
			
			$query .= 'EXISTS(SELECT 1 FROM cars WHERE id = "' . $this->mysqli->escape_string($car->id) . '")';
		}
		
		$query = "SELECT\n" . $query;
		
		$success = $this->mysqli->real_query($query);
		if (!$success)
			throw new Exception("MySQL Error (" . $this->mysqli->errno . "): " . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$result = $this->mysqli->store_result();
		if ($result === false)
			throw new Exception("MySQL Error (" . $this->mysqli->errno . "): " . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$row = $result->fetch_row();
		
		$numCars = count($cars);
		for ($i = 0; $i < $numCars; $i++)
			$cars[$i]->owned = $row[$i] === "1";
		
		$result->close();
	}
	
	function setCarOwned($carID, $owned)
	{
		if ($owned)
		{
			$query = 'SELECT 1 FROM cars WHERE id = "' . $this->mysqli->escape_string($carID) . '"';
			
			$success = $this->mysqli->real_query($query);
			if (!$success)
				throw new Exception("MySQL Error (" . $this->mysqli->errno . "): " . $this->mysqli->error . "\n\nQuery:\n" . $query);
			
			$result = $this->mysqli->store_result();
			if ($result === false)
				throw new Exception("MySQL Error (" . $this->mysqli->errno . "): " . $this->mysqli->error . "\n\nQuery:\n" . $query);
			
			$row = $result->fetch_row();
			
			if (!$row[0])
			{
				$query = 'INSERT INTO cars VALUES ("' . $this->mysqli->escape_string($carID) . '")';
			
				$success = $this->mysqli->real_query($query);
				if (!$success)
					throw new Exception("MySQL Error (" . $this->mysqli->errno . "): " . $this->mysqli->error . "\n\nQuery:\n" . $query);
			}
		}
		else
		{
			$query = 'DELETE FROM cars WHERE id = "' . $this->mysqli->escape_string($carID) . '"';
			
			$success = $this->mysqli->real_query($query);
			if (!$success)
				throw new Exception("MySQL Error (" . $this->mysqli->errno . "): " . $this->mysqli->error . "\n\nQuery:\n" . $query);
		}
	}
	
	function getCarsOwned($limit = 0, $offset = 0)
	{
		$query = 'SELECT id FROM cars';
		if ($limit > 0)
			$query .= ' LIMIT ' . $offset . ', ' . $limit;
		
		$success = $this->mysqli->real_query($query);
		if (!$success)
			throw new Exception("MySQL Error (" . $this->mysqli->errno . "): " . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		$result = $this->mysqli->store_result();
		if ($result === false)
			throw new Exception("MySQL Error (" . $this->mysqli->errno . "): " . $this->mysqli->error . "\n\nQuery:\n" . $query);
		
		
		$carIDs = array();
		while (($row = $result->fetch_row()) !== NULL)
			$carIDs[] = $row[0];
		
		$result->close();
		
		return $carIDs;
	}
	
	
	public function close()
	{
		$this->mysqli->close();
	}
}
?>