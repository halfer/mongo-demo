Mongo demo
===

Introduction
---

This repo demonstrates the storage of schemaless data in the context of bicycles and their components, using MongoDB. The flexible nature of documents means that each component can store just the appropriate properties. For example, a voltage is suitable for a powerpack, and a valve type is suitable for a wheel, but not vice versa.

As it happens, this presages an application I've been thinking about for a while, for which I'd either use Mongo or the JSON column type in PostgreSQL. The value-for-money of a bicycle can in part be determined by looking at the quality (cost) of the components it is fitted with as standard, and is a popular discussion amongst enthusiasts. For example, offroad MTB users are likely to prefer Fox or RockShox suspension units, but manufacturers often specify a cheaper alternative, such as Suntour. Thus, costing the 'bill of materials' on a bike and comparing it to the shop price can be useful when buying.

The demonstration code simply creates a manufacturer collection, and then sets up a nested set of components, with a bike itself being treated as a component. The bike is then rendered to stdout recursively. Finally, an aggregator is demonstrated to sum the values of all components that have a price.

Demo output
---

For reference, the text output of the program `mongo-demo.php` is thus:

	Bikes:
		Haibike SDURO AllMtn RC
			full_build: 1
			components:
				Battery 400Wh
					watt-hours: 400
				Motor
					voltage: 36
					wattage: 250
					manufacturer: Yamaha
					list_price:
						currency: GBP
						value: 300
				Haibike SDURO frame
					material: Aluminium
					size_inches: 27.5
					description: 6061, All MNT, 4-Link System, Yamaha-Interface, hydroforced tubes, 150mm
					list_price:
						currency: GBP
						value: 400
				Haibike SDURO Drivetrain
					speeds: 20
					components:
						Haibike sDuro crank
							material: Aluminium
							gears: 2
							list_price:
								currency: GBP
								value: 45
						Front Derailleur
							manufacturer: Shimano
							list_price:
								currency: GBP
								value: 40
						Rear Derailleur
							manufacturer: Shimano
							line: Deore XT
							model: M 786 Shadow Plus
							gears: 10
							list_price:
								currency: GBP
								value: 50
						Cassette
							description: Sram PG 1020 11-36 Teeth
							list_price:
								currency: GBP
								value: 60
	Components total price: GBP895
