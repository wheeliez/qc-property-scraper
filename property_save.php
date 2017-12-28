<?php

require 'vendor/autoload.php';

use MongoDB\Client;


/**
 * Saves a propertyto MongoDB
 *
 * @param $addressInfo array containing a single property
 */
function saveProperty($propertyInfo, $url, $mongoClient) {

	$collection = $mongoClient->property_scraper->properties;

	$propertyInfo["date_added"] = date('Y-m-d H:i:s');
	$propertyInfo["date_modified"] = null;
	$propertyInfo["eval_url"] = $url;

	$result = $collection->insertOne($propertyInfo);

	$a = isset($propertyInfo["property_identification"]["property_address"]) ? $propertyInfo["property_identification"]["property_address"] : "";
	$n = isset($propertyInfo["property_identification"]["neighborhood"]) ? $propertyInfo["property_identification"]["neighborhood"] : "";
	$address = $a . ", " . $n;

	if ($result) {
		print "property saved : " . $address . "\n";
	}
	else {
		print "property save failed : " . $address . "\n";
	}
}

?>
