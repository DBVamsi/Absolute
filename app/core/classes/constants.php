<?php
/**
 * A class that defines various constant values and configurations used across the RPG.
 * These are typically game settings, item properties, or other static data arrays.
 */
	Class Constants
	{
		// public $PDO; // PDO was not used in this class, removing dependency.

		/**
		 * Constructor for the Constants class.
     * Currently does not require any dependencies.
		 */
		public function __construct()
		{
			// global $PDO; // Removed, PDO is not used by this class.
			// $this->PDO = $PDO;
		}

		/**
		 * @var array Defines the types of currencies available in the game.
     * Each currency has properties like Value (column name), Name (display name), Icon path, and Tradeable status.
		 */
		public $Currency = [
			'Money'				=> [ 
				'Value' => 'Money',
				'Name' => 'Money',
				'Icon' => 'images/Assets/Money.png',
				'Tradeable' => true
			],
			'Abso_Coins'	=> [ 
				'Value' => 'Abso_Coins',
				'Name' => 'ECRPG Coins',
				'Icon' => 'images/Assets/Abso_Coins.png',
				'Tradeable' => true
			],
		];

		/**
		 * Shops
		 */
		public $Shiny_Odds = [
			'pokemon_shop' => 8192,
		];

		/**
		 * Maps
		 */

		/**
		 * Clans
		 */
		public $Clan = [
			"Creation_Cost" => 69420,
		];
		
		/**
		 * Achievements
		 */
		public $Achievements = [
			[
				'Name' 				=> 'Trainer Level',
				'Description' => 'Aquire Trainer Level *.',
				'Tiers' 			=> [ 7, 8, 9, 10, 11, 12 ],
				'Stat' 				=> 'trainer_exp',
				'Display' 		=> '* Exp.',
			],
		];
	}