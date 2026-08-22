<?php

namespace MediaWiki\Extension\UserFlairs\Resolver;

use MediaWiki\Extension\UserFlairs\Cache\FlairCache;
use MediaWiki\Language\Language;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;

/**
 * gets the highest flair for a user from cache
 */
class FlairResolver {

	public function __construct(
		private readonly FlairCache $cache,
		private readonly UserGroupManager $user_group_manager,
		private readonly Language $content_language,
	) {
	}

	/**
	 * @return array{group:string,file:string,url:string,width:int,height:int,priority:int,label:string}|null
	 */
	public function resolve_for_user( UserIdentity $user ): ?array {
		if ( !$user->isRegistered() || $user->getId() <= 0 ) {
			return null;
		}

		return $this->pick( $this->user_group_manager->getUserEffectiveGroups( $user ) );
	}

	/**
	 * @param list<string> $groups
	 * @return array{group:string,file:string,url:string,width:int,height:int,priority:int,label:string}|null
	 */
	public function pick( array $groups ): ?array {
		$catalog = $this->cache->get_catalog();
		if ( $catalog === [] ) {
			return null;
		}

		$best = null;
		foreach ( $groups as $group ) {
			if ( !isset( $catalog[$group] ) ) {
				continue;
			}
			$entry = $this->with_label( $catalog[$group] );
			if ( $best === null ) {
				$best = $entry;
				continue;
			}
			if (
				$entry['priority'] > $best['priority']
				|| (
					$entry['priority'] === $best['priority']
					&& $entry['group'] < $best['group']
				)
			) {
				$best = $entry;
			}
		}

		return $best;
	}

	/**
	 * @return list<array{group:string,file:string,url:string,width:int,height:int,priority:int,label:string}>
	 */
	public function get_catalog(): array {
		$rows = [];
		foreach ( $this->cache->get_catalog() as $entry ) {
			$rows[] = $this->with_label( $entry );
		}
		
		usort( $rows, static function ( array $a, array $b ): int {
			if ( $a['priority'] !== $b['priority'] ) {
				return $b['priority'] <=> $a['priority'];
			}

			return $a['group'] <=> $b['group'];
		} );

		return $rows;
	}

	/**
	 * @param array{group:string,file:string,url:string,width:int,height:int,priority:int,label?:string} $entry
	 * @return array{group:string,file:string,url:string,width:int,height:int,priority:int,label:string}
	 */
	private function with_label( array $entry ): array {
		$entry['label'] = $this->content_language->getGroupName( $entry['group'] );

		return $entry;
	}

}
