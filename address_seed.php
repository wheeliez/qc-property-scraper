<?php

require_once('vendor/autoload.php');
require_once('address_save.php');

use MongoDB\Client;

define("X_START", 220000);
define("Y_START", 5175400);
define("X_END", 255400);
define("Y_END", 5204800);
define("X_INCREMENT", 200);
define("Y_INCREMENT", 200);

$xmin = X_START;
$xmax = X_START + X_INCREMENT;
$ymin = Y_START;
$ymax = Y_START + Y_INCREMENT;
$dryrun = false;

parse_argvs($xmin, $ymin, $xmax, $ymax, $dryrun);

$mongoClient = new Client("mongodb://localhost:27017");

while ($xmin < X_END) {
	while ($ymin < Y_END) {

		$url = build_url($xmin, $ymin, $xmax, $ymax);

		$results = fetch_data($url);

		print "found ". count($results) ." results\n";

		if (!empty($results)) {
			saveAddress($results, $mongoClient, $dryrun);
		}

		$ymin = $ymax;
		$ymax += Y_INCREMENT;
	}

	$ymin = Y_START;
	$ymax = Y_START + Y_INCREMENT;
	$xmin = $xmax;
	$xmax += X_INCREMENT;
}



/**
  * Build url with given coordinates
  * @param string $xmin
  * @param string $ymin
  * @param string $xmax
  * @param string $ymax
  *
  * @return string url build upon the coordinates to fetch properties
  */
function build_url($xmin, $ymin, $xmax, $ymax) {
	print "fetching : (".$xmin.", ".$ymin.") (".$xmax.", ".$ymax.")\n";

	$fields = "ADRESSE,VILLE,URL_FICHE,URL_GOOGLE,CODE_ARROND,NOMARRONDISSEMENT"; //outFields
	$basePath = "http://carte.ville.quebec.qc.ca/ArcGIS/rest/services/CIMobile/Proprietes/MapServer/0/query?f=json&outFields=" . $fields;

	$output = array(
		"xmin" => $xmin,
		"xmax" => $xmax,
		"ymin" => $ymin,
		"ymax" => $ymax,
	);

	$coordinates = urlencode(json_encode($output));

	$url = $basePath . "&geometry=" . $coordinates;

	return $url;
}


/**
  * Return the dataset for a given property's url
  * @param string $url to fetch
  *
  * @return array dataset corresponding to a property
  */
function fetch_data($url) {
	$result = json_decode(file_get_contents($url), true);
	if (array_key_exists("features", $result)) {
		return $result["features"];
	}
	else {
		return array();
	}
}

/**
  * parse params passed to the function's arguments
  * @param string $xmin coordinate
  * @param string $ymin coordinate
  * @param string $xmax coordinate
  * @param string $ymax coordinate
  * @param string $dryrun fetch without saving to database
  *
  */
function parse_argvs(&$xmin, &$ymin, &$xmax, &$ymax, &$dryrun) {

	$longOptions = [
		"help",
		"dryrun",
		"xmin:",
		"xmax:",
		"ymin:",
		"ymax:",
	];

	$options = getopt("", $longOptions);

	if (array_key_exists("help", $options)) {
		foreach ($longOptions as $o) {
			print $o . "\n";
		}
	}

	if (array_key_exists("xmin", $options)
			&& array_key_exists("xmax", $options)
			&& array_key_exists("ymin", $options)
			&& array_key_exists("ymax", $options)) {
		$xmin = $options["xmin"];
		$xmax = $options["xmax"];
		$ymin = $options["ymin"];
		$ymax = $options["ymax"];
	}

	if (array_key_exists("dryrun", $options)) {
		$dryrun = true;
	}
}

?>
