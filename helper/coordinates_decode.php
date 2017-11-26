<?php

function extractCoordinatesFromUrl($url) {
	$jsonCoordinates = array();

	$queryString = parse_url($url, PHP_URL_QUERY);
	$queryParameters = explode("&", $queryString);

	foreach ($queryParameters as $p) {

		$keyValueSeparated = explode("=", $p);
		$key = $keyValueSeparated[0];
		$value = $keyValueSeparated[1];

		if ($key == "geometry") {
			$jsonCoordinates = json_decode(urldecode($value));
		}
	}

	if (count($jsonCoordinates) > 0) {
		$output = array(
			"xmin" => $jsonCoordinates->xmin,
			"xmax" => $jsonCoordinates->xmax,
			"ymin" => $jsonCoordinates->ymin,
			"ymax" => $jsonCoordinates->ymax,
		);

		return $output;
	}
	else {
		print "coordinates not found\n";
	}
}
