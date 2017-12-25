<?php

require_once('vendor/autoload.php');


/**
 * Saves an address with infos to MongoDB
 *
 * @param $addressInfo array containing multiple properties
 */
function saveAddress($addressInfo, $mongoClient, $dryrun) {

	$collection = $mongoClient->property_scraper->addresses_extract;

	foreach ($addressInfo as $v) {

		$data = array(
			"address" 			=> $v["attributes"]["ADRESSE"],
			"city" 				=> getCity($v["attributes"]["VILLE"], $v["attributes"]["NOMARRONDISSEMENT"]),
			"google_maps_url" 	=> $v["attributes"]["URL_GOOGLE"],
			"eval_url"			=> $v["attributes"]["URL_FICHE"],
			"coordinates"		=> extractCoordinatesFromGoogleMapURL($v["attributes"]["URL_GOOGLE"]),
			"date_added"		=> date('Y-m-d H:i:s'),
			"date_modified"		=> null,
		);

		// insted of inserting, to prevent duplicate, we upsert. Udpate || insert with the eval_url key
		$filter = ["eval_url" => $v["attributes"]["URL_FICHE"]];
		$options = ["upsert" => true];

		if (!$dryrun) {
			$result = $collection->replaceOne($filter, $data, $options);

			if ($result->getUpsertedCount() > 0) {
				print "inserted : " . $data["address"] . "\n";
			}
			else if ($result->getModifiedCount() > 0) {
				print "updated : " . $data["address"] . "\n";
			}
			else {
				print "nothing happened... ¯\_(ツ)_/¯ \n";
				var_dump($result);
				var_dump($data);

				exit(1);
			}
		}
	}
}


/**
 * Parse city and neighborhood data and return uniform city name
 *
 * @param string $city city name
 * @param string $neighborhood neighborhood name
 *
 * @return string citu (neighborhood) or city or neighborhood
 */
function getCity($city, $neighborhood) {
	$city = trim($city);
	$neighborhood = trim($neighborhood);

	if (strlen($city) > 0 && strlen($neighborhood) > 0) {
		return $city . " (" . $neighborhood . ")";
	}

	if (strlen($city) > 0) {
		return $city;
	}

	if (strlen($neighborhood) > 0) {
		return $neighborhood;
	}

	return "";
}


/**
 * Extracts XY coordinates from a google map URL with query params
 *
 * @param $url google map url with query (x,y) parameters
 *
 * @return
 */
function extractCoordinatesFromGoogleMapURL($url) {
	$queryString = parse_url($url, PHP_URL_QUERY);
	$params = explode(",", substr($queryString, 2, strlen($queryString) - 2));

	if (count($params) !== 2) {
		return null;
	}

	return array("x" => $params[0], "y" => $params[1]);
}
