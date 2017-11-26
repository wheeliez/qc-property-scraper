<?php

require 'vendor/autoload.php';

use MongoDB\Client;


/**
 * Saves a propertyto MongoDB
 *
 * @param $addressInfo array containing a single property
 */
function saveProperty($propertyInfo) {

	$client = new Client("mongodb://localhost:27017");
	$collection = $client->property_scraper->properties;

	$propertyInfo["date_added"] = date('Y-m-d H:i:s');
	$propertyInfo["date_modified"] = null;

	$result = $collection->insertOne($propertyInfo);

	if ($result) {
		print "property saved : " . $propertyInfo["property_identification"]["property_address"] . ", " . $propertyInfo["property_identification"]["neighborhood"] . "\n";
	}
	else {
		print "property save failed : " . $propertyInfo["property_identification"]["property_address"] . ", " . $propertyInfo["property_identification"]["neighborhood"] . "\n";
	}
}

?>
