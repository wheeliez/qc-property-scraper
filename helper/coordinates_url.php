<?php

require_once('coordinates_decode.php');

if (count($argv) > 1) {
	$coordinates = extractCoordinatesFromUrl($argv[1]);

var_dump($coordinates);
	print "\n";
	print "json coordinates : " . json_encode($coordinates) . "\n";
	print "url encoded coordinates : " . urlencode(json_encode($coordinates)) . "\n";
}
else {
	print "url not found\n";
}

?>
