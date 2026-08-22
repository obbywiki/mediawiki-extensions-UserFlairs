<?php

namespace MediaWiki\Extension\UserFlairs\Cache;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\UserFlairs\File\FlairFileLookup;
use MediaWiki\Extension\UserFlairs\Store\FlairStore;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * WAN cache for the group flairs (including resolved thumbs)
 */
class FlairCache {

	public const CONSTRUCTOR_OPTIONS = [
		'UserFlairsCacheTTL',
	];

	private const KEYSPACE = 'uf';

	private int $ttl;

	public function __construct(
		private readonly WANObjectCache $wan_cache,
		private readonly FlairStore $store,
		private readonly FlairFileLookup $file_lookup,
		ServiceOptions $options,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->ttl = max( 1, (int)$options->get( 'UserFlairsCacheTTL' ) );
	}

	/**
	 * @return array<string,array{group:string,file:string,url:string,width:int,height:int,priority:int,label?:string}>
	 */
	public function get_catalog(): array {
		$key = $this->catalog_key();

		return $this->wan_cache->getWithSetCallback( $key, $this->ttl,
			function () {
				$catalog = [];
				foreach ( $this->store->get_all() as $row ) {
					$described = $this->file_lookup->describe( $row['file'] );
					if ( $described === null ) {
						continue;
					}

					$catalog[$row['group']] = [
						'group' => $row['group'],
						'file' => $described['file'],
						'url' => $described['url'],
						'width' => $described['width'],
						'height' => $described['height'],
						'priority' => $row['priority'],
					];
				}

				return $catalog;
			}
		);
	}

	public function purge(): void {
		$this->wan_cache->delete( $this->catalog_key() );
	}

	private function catalog_key(): string {
		return $this->wan_cache->makeKey( self::KEYSPACE, 'catalog' );
	}

}
