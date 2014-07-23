<?php

/*
The sql instance details will be availbale in the environment variable.
heroku make it really easy :)
*/

require_once("config.php");

function mysqli_do($q) {
  
  /*Parsing details from heroku*/
  $url = parse_url(getenv("CLEARDB_DATABASE_URL"));
	$c = mysqli_connect($url["host"],$url["user"], $url["pass"]) or die(mysqli_error($c));
	mysqli_select_db($c, substr($url["path"],1)) or die(mysqli_error($c));
	$r = mysqli_query($c, $q) or die(mysqli_error($c));
	mysqli_close($c);
	return $r;
}