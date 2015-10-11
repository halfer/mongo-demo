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
$manuCollection = getManufacturerCollection($db);

createManufacturer($manuCollection, "Haibike");		// Of the bike itself
createManufacturer($manuCollection, "Yamaha");		// Component manufacturers
createManufacturer($manuCollection, "Shimano");
createManufacturer($manuCollection, "Fox");
createManufacturer($manuCollection, "Selle Royal");
createManufacturer($manuCollection, "FSA");

// Let's create some components
$compCollection = getComponentCollection($db);
$ids = [];
$ids[] = createDocument($compCollection, "Battery 400Wh", ['watt_hours' => 400, ]);
$ids[] = createDocument($compCollection, "Motor",
	[
		'voltage' => 36, 'wattage' => 250,
		'manufacturer' => getManufacturerId($manuCollection, 'yamaha'),
	]
);
$ids[] = createDocument($compCollection, "Haibike SDURO frame",
	[
		'material' => 'Aluminium',
		'size_inches' => 27.5,
		'description' => "6061, All MNT, 4-Link System, Yamaha-Interface, hydroforced tubes, 150mm"
	]
);

// Special group for the drivetrain
$dtIds = [];
$dtIds[] = createDocument($compCollection, 'Haibike sDuro crank',
	['material' => 'Aluminium', ]
);
$dtIds[] = createDocument($compCollection, 'Front Derailleur',
	[
		'manufacturer' => getManufacturerId($manuCollection, 'shimano'),
		'gears' => 2,
	]
);
$dtIds[] = createDocument($compCollection, "Rear Derailleur",
	[
		'manufacturer' => getManufacturerId($manuCollection, 'shimano'),
		'line' => 'Deore XT',
		'model' => 'M 786 Shadow Plus',
		'gears' => 10,
	]
);
$dtIds[] = createDocument($compCollection, "Cassette",
	[
		'description' => 'Sram PG 1020 11-36 Teeth',
	]
);

// Finally put the drivetrain together
$ids[] = createDocument($compCollection, "Haibike SDURO Drivetrain",
	['speeds' => 20, 'components' => createIdsGroup($dtIds), ]
);

// Let's create a full bike
createDocument($compCollection, "Haibike SDURO AllMtn RC",
	['full-build' => true, 'components' => createIdsGroup($ids), ]
);

// Iterate through the stored data
echo "Manufacturers:\n";
dumpCollection($manuCollection);

// Show the full builds, which include all of the above components
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
		echo "\t{$document['name']}\n";

		dumpRecursive($collection->db, $document);
	}
}

/**
 * Recursively renders a container (e.g. a mongo document or an array element)
 * 
 * @param MongoDB $db
 * @param mixed $container
 * @param integer $level
 */
function dumpRecursive(MongoDB $db, $container, $level = 1)
{
	// Get collections we need
	$components = getComponentCollection($db);
	$manufacturers = getManufacturerCollection($db);

	foreach ($container as $key => $value)
	{
		// Skip uninteresting properties
		if ($key == '_id' || $key == 'name')
		{
			continue;
		}

		// Render indent suitable to recurse level
		echo str_repeat("\t", $level + 1);

		if (is_array($value))
		{
			// Render items in the next level down
			echo "{$key}:\n";
			dumpRecursive($db, $value, $level + 1);
		}
		else
		{
			if ($key == 'manufacturer')
			{
				// Get manufacturer metadata
				$document = $manufacturers->findOne(['_id' => getMongoIdObject($value)]);
				echo "manufacturer: {$document['name']}\n";
			}
			elseif (isMongoRef($value))
			{
				// Render mongo ref from the components collection
				$document = $components->findOne(['_id' => getMongoIdObject($value)]);

				// If the component has a name, use that as a subheading
				echo isset($document['name']) ? $document['name'] : '<component>';
				echo "\n";

				// ... and then render the component properties
				dumpRecursive($db, $document, $level + 1);
			}
			else
			{
				// Render scalar value
				echo "{$key}: {$value}\n";
			}
		}
	}
}

/**
 * Deletes all our known collections
 * 
 * @param MongoDB $db
 */
function zapDatabase(MongoDB $db)
{
	getComponentCollection($db)->drop();
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

/**
 * Marks an ID group as mongo IDs
 */
function createIdsGroup(array $group)
{
	foreach ($group as &$id)
	{
		$id = 'mongoid:' . $id;
	}

	return $group;
}

/**
 * Gets a manufacturer ID for a shortname
 * 
 * @param string $shortName
 * @return string
 */
function getManufacturerId(MongoCollection $manuCollection, $shortName)
{
	$document = $manuCollection->findOne(['shortname' => $shortName, ]);

	$id = null;
	if (isset($document['_id']->{'$id'}))
	{
		$obj = $document['_id'];
		$id = 'mongoid:' . $obj->{'$id'};
	}

	// Bork if an ID is not found
	if (!$id)
	{
		trigger_error(
			sprintf("Manufacturer `%s` not found - check spelling?", $shortName),
			E_USER_ERROR
		);
	}

	return $id;
}

function isMongoRef($value)
{
	return strpos($value, 'mongoid:') === 0;
}

function getMongoIdObject($value)
{
	$id = str_replace('mongoid:', '', $value);

	return new MongoId($id);
}

/**
 * Gets the component collection
 * 
 * @param MongoDB $db
 * @return MongoCollection
 */
function getComponentCollection(MongoDB $db)
{
	return $db->component;
}

/**
 * Gets the manufacturer collection
 * 
 * @param MongoDB $db
 * @return MongoCollection
 */
function getManufacturerCollection(MongoDB $db)
{
	return $db->manufacturer;
}
