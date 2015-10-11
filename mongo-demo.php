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
 * 
 * Components can contain components too, if it is felt necessary. For example rather than
 * a bike->crank and bike->chain relationships, it may be cleaner to group them together in
 * a drivetrain subgroup, e.g. bike->drivetrain->crank, bike->drivetrain->chain, especially
 * if this drivetrain set is used in other bikes.
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
$ids[] = createDocument($compCollection, "Haibike SDURO frame",
	[
		'material' => 'Aluminium',
		'size_inches' => 27.5,
		'description' => "6061, All MNT, 4-Link System, Yamaha-Interface, hydroforced tubes, 150mm"
	]
);
// @todo This drivetrain needs splitting up into several components
$ids[] = createDocument($compCollection, "Gears", 
	[
		'speeds' => 10,
		'description' => "Rear Derailleur: Shimano Deore XT M 786 Shadow Plus, 20 Speed, Cassette: Sram PG 1020 11-36 Teeth"]
);

// Let's create a full bike
createDocument($compCollection, "Haibike SDURO AllMtn RC",
	['full-build' => true, 'components' => $ids, ]
);

// Iterate through the stored data
echo "All components (including groups and bike builds):\n";
dumpCollection($compCollection);
echo "Manufacturers:\n";
dumpCollection($manuCollection);

// Full builds
echo "Bikes:\n";
dumpCollection($compCollection, ['full-build' => true, ]);

/**
 * Lists everything in the specified collection
 * 
 * @param MongoCollection $collection
 */
function dumpCollection(MongoCollection $collection, $query = [])
{
	// Find things in this collection matching the supplied query
	$cursor = $collection->find($query);

	foreach ($cursor as $document)
	{
		#print_r($document);
		echo "\t{$document['name']}\n";

		// Render all the interesting properties of this doc
		foreach ($document as $key => $value)
		{
			if ($key == '_id' || $key == 'name')
			{
				continue;
			}

			echo "\t\t{$key}: ";
			if (is_array($value))
			{
				// Don't render nested items yet
				echo "<group>\n";
			}
			else
			{
				// Render scalar value
				echo "{$value}\n";
			}
		}
		echo "\n";
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
	$id = createDocument($collection, $name, $allProps);

	return $id;
}

/**
 * Creates a document in a collection
 * 
 * I'm inserting here using 'Acknowledged' write concerns
 * 
 * @param MongoCollection $collection
 * @param string $name
 * @param array $properties
 */
function createDocument(MongoCollection $collection, $name, array $properties = [])
{
	$allProps = array_merge($properties, ['name' => $name, ]);
	$collection->insert(
		$allProps,
		['w' => 1, ]
	);

	// We should have a generated ID now
	$id = null;
	if (isset($allProps['_id']->{'$id'}))
	{
		$obj = $allProps['_id'];
		$id = $obj->{'$id'};
	}

	return $id;
}
