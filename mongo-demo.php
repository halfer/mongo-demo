<?php

/* 
 * A demo of a schemaless Mongo structure
 * 
 * I'm modelling an e-bike, and its constituent parts, from this URL:
 * 
 * http://www.onbike.co.uk/electricbikes/haibike-sduro-allmtn-rc/
 * 
 * Bikes and their components will be stored in "components", and will of course have very
 * different properties. For example, a motor will have a voltage property, but this would
 * not be useful for a frame, which will have a colour property.
 */

$m = new MongoClient();

// select a database and clear it
$db = $m->bikes;
zapDatabase($db);

// Let's create some component manufacturers
$manuCollection = $db->manufacturer;

createManufacturer($manuCollection, "Haibike");		// Of the bike itself
createManufacturer($manuCollection, "Yamaha");		// Component manufacturers
createManufacturer($manuCollection, "Shimano");
createManufacturer($manuCollection, "Fox");
createManufacturer($manuCollection, "Selle Royal");
createManufacturer($manuCollection, "FSA");

// Let's create some components
$compCollection = $db->component;
$ids = [];
$ids[] = createDocument($compCollection, "Battery 400Wh", ['watt_hours' => 400, ]);
$ids[] = createDocument($compCollection, "Motor",
	['voltage' => 36, 'wattage' => 250, 'manufacturer' => 'yahama', ]
);

// Iterate through the stored data
echo "Components:\n";
dumpCollection($compCollection);
echo "Manufacturers:\n";
dumpCollection($manuCollection);

/**
 * Lists everything in the specified collection
 * 
 * @param MongoCollection $collection
 */
function dumpCollection(MongoCollection $collection)
{
	// Find everything in this collection
	$cursor = $collection->find();

	foreach ($cursor as $document)
	{
		echo "\t{$document['name']}\n";
	}
}

/**
 * Deletes all our known collections
 * 
 * @param MongoDB $db
 */
function zapDatabase(MongoDB $db)
{
	$db->component->drop();
	$db->manufacturer->drop();
}

/**
 * Inserts an item into the manufacturer collection, with a shortname
 * 
 * @param MongoCollection $collection
 * @param string $name
 * @param array $properties
 */
function createManufacturer(MongoCollection $collection, $name, array $properties = [])
{
	$shortname = str_replace(' ', '-', strtolower($name));
	$allProps = array_merge($properties, ['shortname' => $shortname, ]);
	createDocument($collection, $name, $allProps);
}

/**
 * Creates a document in a collection
 * 
 * @param MongoCollection $collection
 * @param string $name
 * @param array $properties
 */
function createDocument(MongoCollection $collection, $name, array $properties = [])
{
	$allProps = array_merge($properties, ['name' => $name, ]);
	$collection->insert($allProps);
}
