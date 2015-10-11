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

// Load libraries
$root = __DIR__;
require_once $root . '/functions.php';

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
$ids[] = createDocument($compCollection, "Battery 400Wh", ['watt-hours' => 400, ]);
$ids[] = createDocument($compCollection, "Motor",
	[
		'voltage' => 36, 'wattage' => 250,
		'manufacturer' => getManufacturerId($manuCollection, 'yamaha'),
		'list_price' => ['currency' => 'GBP', 'value' => 300, ]
	]
);
$ids[] = createDocument($compCollection, "Haibike SDURO frame",
	[
		'material' => 'Aluminium',
		'size_inches' => 27.5,
		'description' => "6061, All MNT, 4-Link System, Yamaha-Interface, hydroforced tubes, 150mm",
		'list_price' => ['currency' => 'GBP', 'value' => 400, ]
	]
);

// Special group for the drivetrain
$dtIds = [];
$dtIds[] = createDocument($compCollection, 'Haibike sDuro crank',
	[
		'material' => 'Aluminium',
		'gears' => 2,
		'list_price' => ['currency' => 'GBP', 'value' => 45, ]
	]
);
$dtIds[] = createDocument($compCollection, 'Front Derailleur',
	[
		'manufacturer' => getManufacturerId($manuCollection, 'shimano'),
		'list_price' => ['currency' => 'GBP', 'value' => 40, ]
	]
);
$dtIds[] = createDocument($compCollection, "Rear Derailleur",
	[
		'manufacturer' => getManufacturerId($manuCollection, 'shimano'),
		'line' => 'Deore XT',
		'model' => 'M 786 Shadow Plus',
		'gears' => 10,
		'list_price' => ['currency' => 'GBP', 'value' => 50, ]
	]
);
$dtIds[] = createDocument($compCollection, "Cassette",
	[
		'description' => 'Sram PG 1020 11-36 Teeth',
		'list_price' => ['currency' => 'GBP', 'value' => 60, ]
	]
);

// Finally put the drivetrain together
$ids[] = createDocument($compCollection, "Haibike SDURO Drivetrain",
	['speeds' => 20, 'components' => createIdsGroup($dtIds), ]
);

// Let's create a full bike
createDocument($compCollection, "Haibike SDURO AllMtn RC",
	['full_build' => true, 'components' => createIdsGroup($ids), ]
);

// Show the full builds, which include all of the above components
echo "Bikes:\n";
dumpCollection($compCollection, ['full_build' => true, ]);

// Use an aggregation pipeline to sum the prices of the components
$pipeline = [
	// Only include components that have a price
	['$match' => ['list_price' => ['$exists' => true, ]]],
	// Create a projection that contains the value child of the price subdocument
	['$project' => [
			'price' => '$list_price.value',
	]],
	// We do the summing here
	['$group' => ['_id' => null, 'total' => ['$sum' => '$price', ]]],
];
$result = $compCollection->aggregate($pipeline);

if (isset($result['result'][0]['total']))
{
	$price = $result['result'][0]['total'];
	echo "Components total price: GBP{$price}\n";
}

/**
 * Interesting extensions:
 * 
 * > Group by the currency - should be possible with aggregation
 * > Deriving a component sum just for one build would be trickier - expect that would
 *		need map-reduce functions in JavaScript, since the components are referenced
 *		recursively.
 */
