<?php

/* 
 * Utility functions for this Mongo demo
 */

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
