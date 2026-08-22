<?php

namespace MediaWiki\Extension\UserFlairs\File;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Title\Title;
use RepoGroup;

/**
 * glorified thumbnail resolver
 */
class FlairFileLookup {

	public const CONSTRUCTOR_OPTIONS = [
		'UserFlairsSize',
	];

	private int $size;

	public function __construct(
		private readonly RepoGroup $repo_group,
		ServiceOptions $options,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->size = max( 1, (int)$options->get( 'UserFlairsSize' ) );
	}
	
	private function normalize_file_name( string $file_name ): string {
		$file_name = str_replace( '_', ' ', trim( $file_name ) );
		if ( $file_name === '' ) { return ''; }

		$title = Title::newFromText( $file_name, NS_FILE );
		if ( $title === null || !$title->inNamespace( NS_FILE ) ) { return ''; }

		return $title->getDBkey();
	}

	/**
	 * @return array{file:string,url:string,width:int,height:int}|null
	 */
	public function describe( string $file_name ): ?array {
		$dbkey = $this->normalize_file_name( $file_name );
		if ( $dbkey === '' ) { return null; }

		$title = Title::makeTitle( NS_FILE, $dbkey );
		$file = $this->repo_group->findFile( $title );
		if ( !$file || !$file->exists() || !$file->canRender() ) { return null; }

		$transform = $file->transform( [
			'width' => $this->size,
			'height' => $this->size,
		] );

		if ( !$transform || $transform->isError() ) {
			$url = $file->getUrl();
			$width = (int)$file->getWidth();
			$height = (int)$file->getHeight();
		} else {
			$url = $transform->getUrl();
			$width = (int)$transform->getWidth();
			$height = (int)$transform->getHeight();
		}

		if ( $url === '' ) { return null; }

		return [
			'file' => $title->getText(),
			'url' => $url,
			'width' => $width > 0 ? $width : $this->size,
			'height' => $height > 0 ? $height : $this->size,
		];
	}

	public function get_size(): int {
		return $this->size;
	}

}
