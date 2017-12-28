<?php

require_once('vendor/autoload.php');
require_once('property_save.php');

use Goutte\Client as CrawlerClient;
use MongoDB\Client as MongoClient;
use Symfony\Component\DomCrawler\Crawler as Crawler;


getPropertiesAndParseResults(new CrawlerClient(), new MongoClient("mongodb://localhost:27017"));

function getPropertiesAndParseResults(CrawlerClient $crawlerClient, MongoClient $mongoClient) {

	$alreadyParsedProperties = $mongoClient->property_scraper->properties->distinct("eval_url");

	$cursor = $mongoClient->property_scraper->addresses_extract->find([ "eval_url" => [ '$nin' => $alreadyParsedProperties ] ], [ 'noCursorTimeout' => true ]);
	$it = new IteratorIterator($cursor);
	$it->rewind();

	while($doc = $it->current()) {
	    $evaluationURL = $doc["eval_url"];

		$crawler = $crawlerClient->request('GET', $evaluationURL);

		$nbrProperties = numberPropertiesInLink($crawler);

		if ($nbrProperties > 1) {
			$propertiesData = extractMultiplePropertiesData($crawler, $crawlerClient, $evaluationURL);

			foreach ($propertiesData as $propertyData) {
				saveProperty($propertyData, $evaluationURL, $mongoClient);
			}

		}
		else {
			$propertyData = extractData($crawler);
			saveProperty($propertyData, $evaluationURL, $mongoClient);
		}

		$it->next();
	}
}



/**
 * Extract for page with multiple properties at the same address, for example condo or appt blocs
 * @param Crawler $crawler of the page containing multiple properties
 * @param Client $client of the  request previously made
 * @param String $url of the base request
 *
 * @return Array of properties data
 */
function extractMultiplePropertiesData(Crawler $propertiesCrawler, CrawlerClient $client, $url) {
	$propertiesInLot = array();
	$propertiesExtractedData = array();

	$propertySelect = $propertiesCrawler->filter('#ctl00_ctl00_contenu_texte_page_fichePropriete_RechercheAdresse1_ddChoix option');
	$propertySelect->each(function($node) use (&$propertiesInLot) {
		$propertiesInLot[] = trim($node->filter("option")->attr('value'));
	});

	// with the properties value we fetched, create a new request for each one
	foreach ($propertiesInLot as $p) {
		$crawler = $client->request('GET', $url);
		$form = $crawler->selectButton('ctl00$ctl00$contenu$texte_page$fichePropriete$RechercheAdresse1$btnChoix')->form();
		$crawler = $client->submit($form, array(
			'ctl00$ctl00$contenu$texte_page$fichePropriete$RechercheAdresse1$ddChoix' => $p
		));

		$propertiesExtractedData[] = extractData($crawler);
	}

	return $propertiesExtractedData;
}

/**
  * Crawl a page and return the associated data
  * @param Crawler $crawler of the current page to extract data from
  *
  * @return Array property data
  */
function extractData(Crawler $crawler) {
	$propertyIdentification = array();
	$owners = array();
	$landLot = array();
	$house = array();
	$evaluation = array();

	$attributes = $crawler->filter(".table_identification_unite tr")->each(function($node) use (&$propertyIdentification) {
		$text = $node->filter("th")->text();
		$value = $node->filter("td")->text();
		$key = identificationKeysFromText($text);

	 	if ($key) {
			$propertyIdentification[$key] = $value;
		}
	});

	$crawler->filter(".table_proprietaire tr")->each(function ($node) use (&$owners) {
		$text = $node->filter("th")->text();
		$value = $node->filter("td")->text();
		$key = ownershipKeysFromText($text);

		$person = array();
		if ($key == "name") {
			$person = array($key => $value);
			$owners["owners"][] = $person;
	 	}
		else if ($key == "postal_address") {
			// parse address to remove br tags
			$addressHtml = $node->filter("td")->html();
			$explodedAddressArray = explode("<br>", $addressHtml);
			for ($i = 0; $i < count($explodedAddressArray); $i++) {
				$explodedAddressArray[$i] = trim($explodedAddressArray[$i]);
			}
			$value = implode(", ", $explodedAddressArray);

			if (!is_null($owners["owners"])) {
				$person = end($owners["owners"]);
				array_pop($owners["owners"]);
				$person[$key] = $value;
				$owners["owners"][] = $person;
			}

		}
		else if ($key) {
			$owners[$key] = $value;
		}
	});

	$attributes = $crawler->filter(".table_caracteristiques_terrain tr")->each(function($node) use (&$landLot) {
		$text = $node->filter("th")->text();
		$value = $node->filter("td")->text();
		$key = lotKeysFromText($text);

		if ($key == "frontage") {
			// remove measure unit - remove the last char
			$value = substr($value, 0, strlen($value) - 1);
		}
		else if ($key == "area") {
			// remove measure unit - find the decimal point and only keep the 2 following chars
			$endPos = strpos($value, ".") + 3 ;
			$value = trim(substr($value, 0, $endPos));
		}
		else if ($key == "agricultural_zoning") {
			// transfer to bool if agricultural_zoning or not
			$value = !($value == "Non zoné");
		}

		if ($key) {
			$landLot[$key] = $value;
		}
	});

	$attributes = $crawler->filter(".table_caracteristiques_batiment tr")->each(function($node) use (&$house) {
		$text = $node->filter("th")->text();
		$value = $node->filter("td")->text();
		$key = houseKeysFromText($text);

		if ($key == "floor_area") {
			// remove measure unit - find the decimal point and only keep the 2 following chars
			$endPos = strpos($value, ".") + 3 ;
			$value = trim(substr($value, 0, $endPos));
		}

		if ($key) {
			$house[$key] = $value;
	 	}
	});

	$attributes = $crawler->filter(".table_valeurs_role tr")->each(function($node) use (&$evaluation) {
		$text = $node->filter("th")->text();
		$value = trim($node->filter("td")->text());
		$key = evaluationKeysFromText($text);

		// remove dollar sign
		$dollarPos = strpos($value, "$");
		if ($dollarPos !== false) {
			$value = substr($value, 0, $dollarPos - 1);
			$value = str_replace(" ", "", $value);
		}

		if ($key) {
			$evaluation[$key] = $value;
		}
	});

	$property = array();
	$property["property_identification"] = $propertyIdentification;
	$property["owners"] = $owners;
	$property["land_lot"] = $landLot;
	$property["house"] = $house;
	$property["evaluation"] = $evaluation;

	return $property;
}


/**
  * Return the number of properties listed in a page
  * @param Crawler $crawler of the current page to verify
  *
  * @return Integer number of properties
  */
function numberPropertiesInLink(Crawler $crawler) {
	$array = $crawler->filter('#ctl00_ctl00_contenu_texte_page_fichePropriete_RechercheAdresse1_ddChoix')->evaluate('count(option)');
	return count($array) > 0 ? reset($array) : 1;
}


/**
  * Return a key for the scraped text to properly represent the values
  * @param string $text the text to parse
  *
  * @return string the key representing the parsed text
  */
function identificationKeysFromText($text) {
	$text = trim($text);

	if ($text == "Adresse") {
		return "property_address";
	}
	else if ($text == "Arrondissement") {
		return "neighborhood";
	}
	else if ($text == "Numéro de lot") {
		return "lot_nbr";
	}
	else if ($text == "Numéro matricule") {
		return "registration_nbr";
	}
	else if ($text == "Utilisation prédominante") {
		return "usage_type";
	}
	else if ($text == "Numéro d'unité voisinage") {
		return "neighborhood_nbr";
	}
	else if ($text == "Dossier no") {
		return "file_nbr";
	}
}

function ownershipKeysFromText($text) {
	$text = trim($text);

	if ($text == "Nom") {
		return "name";
	}
	else if ($text == "Adresse postale") {
		return "postal_address";
	}
	else if ($text == "Date d'inscription au rôle") {
		return "inscription_date";
	}
	else if ($text == "Condition particulière d'inscription") {
		return "inscription_condition";
	}
}

function lotKeysFromText($text) {
	$text = trim($text);

	if ($text == "Mesure frontale") {
		return "frontage";
	}
	else if ($text == "Superficie") {
		return "area";
	}
	else if ($text == "Zonage agricole") {
		return "agricultural_zoning";
	}
}

function houseKeysFromText($text) {
	$text = trim($text);

	if ($text == "Nombre d'étage") {
		return "nbr_floors";
	}
	else if ($text == "Année de construction") {
		return "construction_year";
	}
	else if ($text == "Aire d'étages") {
		return "floor_area";
	}
	else if ($text == "Genre de construction") {
		return "construction_type";
	}
	else if ($text == "Lien physique") {
		return "physical_link";
	}
	else if ($text == "Nombre de logements") {
		return "nbr_appartments";
	}
	else if ($text == "Nombre de locaux non-résidentiels") {
		return "nbr_commercial_appartments";
	}
	else if ($text == "Nombre de chambres locatives") {
		return "nbr_rent_rooms";
	}
}

function evaluationKeysFromText($text) {
	$text = trim($text);

	if ($text == "Date de référence du marché") {
		return "reference_date";
	}
	else if ($text == "Valeur du terrain") {
		return "lot_value";
	}
	else if ($text == "Valeur du bâtiment") {
		return "building_value";
	}
	else if ($text == "Valeur de l'immeuble au rôle antérieur") {
		return "last_role_value";
	}
}

?>
