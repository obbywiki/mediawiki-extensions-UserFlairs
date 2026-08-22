<?php

namespace MediaWiki\Extension\UserFlairs\Html;

class FlairPlacement {

	/** @var array<int,true> */
	private array $placed_user_ids = [];

	public function mark( int $user_id ): void {
		if ( $user_id <= 0 ) { return; }
		$this->placed_user_ids[$user_id] = true;
	}

	public function is_placed( int $user_id ): bool {
		return isset( $this->placed_user_ids[$user_id] );
	}

}
