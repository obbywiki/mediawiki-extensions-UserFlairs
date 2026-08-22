<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\UserFlairs\Cache\FlairCache;
use MediaWiki\Extension\UserFlairs\File\FlairFileLookup;
use MediaWiki\Extension\UserFlairs\Html\FlairHtml;
use MediaWiki\Extension\UserFlairs\Html\FlairPlacement;
use MediaWiki\Extension\UserFlairs\Resolver\FlairResolver;
use MediaWiki\Extension\UserFlairs\Store\FlairStore;
use MediaWiki\MediaWikiServices;

/** @phpcs-require-sorted-array */
return [
	'UserFlairs.FlairCache' => static function ( MediaWikiServices $services ): FlairCache {
		return new FlairCache(
			$services->getMainWANObjectCache(),
			$services->get( 'UserFlairs.FlairStore' ),
			$services->get( 'UserFlairs.FlairFileLookup' ),
			new ServiceOptions(
				FlairCache::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			)
		);
	},

	'UserFlairs.FlairFileLookup' => static function ( MediaWikiServices $services ): FlairFileLookup {
		return new FlairFileLookup(
			$services->getRepoGroup(),
			new ServiceOptions(
				FlairFileLookup::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			)
		);
	},

	'UserFlairs.FlairHtml' => static function ( MediaWikiServices $services ): FlairHtml {
		return new FlairHtml(
			$services->get( 'UserFlairs.FlairFileLookup' )
		);
	},

	'UserFlairs.FlairPlacement' => static function (): FlairPlacement {
		return new FlairPlacement();
	},

	'UserFlairs.FlairResolver' => static function ( MediaWikiServices $services ): FlairResolver {
		return new FlairResolver(
			$services->get( 'UserFlairs.FlairCache' ),
			$services->getUserGroupManager(),
			$services->getContentLanguage()
		);
	},

	'UserFlairs.FlairStore' => static function ( MediaWikiServices $services ): FlairStore {
		return new FlairStore(
			$services->getConnectionProvider()
		);
	}
];
