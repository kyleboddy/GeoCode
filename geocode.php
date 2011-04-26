<?php

/**
 * Geolocation script using Google Maps v3 API.
 *
 * Set your variables accordingly in lines 17-23, and edit the column names as necessary to match your DB schema.
 * 
 * @package    GeoCode
 * @author     Kyle Boddy <kyle.boddy@gmail.com>
 * @license    Creative Commons Share-Alike 3.0 (http://creativecommons.org/licenses/by-sa/3.0/)
 * @version    Release: 1.0
 * @link       http://pear.php.net/package/PackageName
 */

class GeoCode {
	
	// variables to set
	var $region = '';
	var $server = '';
	var $user = '';
	var $pass = '';
	var $db = '';
	var $table = '';
	
	// connect to the database
	// returns void
	function dbsetup()
	{
		// connect to the database
		$link = mysql_connect($this->server, $this->user, $this->pass);
		
		if (!$link) {
		    die(mysql_error());
		}
		
		// select the database
		mysql_select_db($this->db);
		
	}
	
	// geocode an address
	// returns $coords (array)
	function geolocate($address)
	{
		$lat = 0;
		$lng = 0;
		
		$data_location = "http://maps.google.com/maps/api/geocode/json?address=".str_replace(" ", "+", $address)."&sensor=false";
		
		if ($this->region!="" && strlen($this->region)==2) { $data_location .= "&region=".$this->region; }
		$data = file_get_contents($data_location);
		
		// turn this on to see if we are being blocked
		// echo $data;
		
		$data = json_decode($data);
		
		if ($data->status=="OK") {
			$lat = $data->results[0]->geometry->location->lat;
			$lng = $data->results[0]->geometry->location->lng;
		}
		
		// concatenate lat/long coordinates
		$coords['lat'] = $lat;
		$coords['lng'] = $lng;
		
		return $coords;
	}
	
	// gets all addresses from a table, uses address1 / city / state / zip (change these if your column names are different)
	// checks for addresses that are not yet geolocated (bg_lat, bg_long are empty)
	// returns $result (MySQL object)
	function getAddresses()
	{
		// connect to the database
		$this->dbsetup();
		
		$query = "SELECT address1, city, state, zip FROM " . $this->table . " WHERE bg_lat = '' AND bg_long = ''";
		
		$result = mysql_query($query);
		
		return $result;
	}
	
	// updates the database with geolocated coordinates where the address is equivalent
	// echoes out the UPDATE query for quality control and visualization
	// returns void
	function updatedb($lat, $lng, $address)
	{
		$query = "UPDATE " . $this->table . "  SET bg_lat = '". $lat ."', bg_long = '". $lng ."' WHERE address1 = '". $address ."'";
		$result = mysql_query($query);
		echo $query . "\n";
	}
	
	// simple function used with array_walk() to escape values in preparation for insertion to the database
	// returns void
	function mysql_escape(&$value)
	{
		$value = mysql_real_escape_string($value);
	}
	
	// main function
	// returns void
	function invoke()
	{
		// get the list of addresses
		$results = $this->getAddresses();
		
		$coords = array();
		
		while ($row = mysql_fetch_array($results))
		{
			// escape the data recursively
			array_walk($row, array($this, 'mysql_escape'));
			
			// if there is a # sign for a suite number, ignore it (google has problems with these), otherwise use the address
			if (strpos($row['address1'], '#'))
			{
				$addresses = explode('#',$row['address1']);
				$address = $addresses[0];
			}
			else
			{
				$address = $row['address1'];
			}
			
			// concatenate an address line for geolocation using commas
			$addressline = $address . ', ' . $row['city'] . ', ' . $row['state'] . ', ' . $row['zip'];
			
			// ship it off to google
			$coords = $this->geolocate($addressline);
			
			// update the database with the coordinates
			$this->updatedb($coords['lat'], $coords['lng'], $row['address1']);
		}
	}
}

// invoke the class
$geoCode = new GeoCode();
$geoCode->invoke();

?>