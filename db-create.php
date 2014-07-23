<?php

/* Dirty Hack to create the tables on heroku .
  delete this file after the database is created .
  will add a proper implementation in future versions .*/

require_once ("mysqli.inc.php");

$querry = "CREATE TABLE `push` (
  `id` MEDIUMINT NOT NULL AUTO_INCREMENT,
  `token` varchar(70) DEFAULT NULL,
  `userid` MEDIUMINT DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ";



var_dump(mysqli_do($querry));

?>
