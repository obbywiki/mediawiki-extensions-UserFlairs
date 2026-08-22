<?php

namespace MediaWiki\Extension\UserFlairs\Html;

use MediaWiki\Extension\UserFlairs\File\FlairFileLookup;
use MediaWiki\Html\Html;

class FlairHtml {

	public const MODE_IN_AVATAR = 'in_avatar';
	public const MODE_RELOCATABLE = 'relocatable';

	public function __construct( private readonly FlairFileLookup $file_lookup ) {}

	/**
	 * @param array{group:string,file:string,url:string,width:int,height:int,priority:int,label?:string} $flair
	 */
	public function render( array $flair, string $mode = self::MODE_IN_AVATAR ): string {
		$classes = [ 'uf-flair' ];
		if ( $mode === self::MODE_RELOCATABLE ) {
			$classes[] = 'uf-flair--relocatable';
		}

		$size = $this->file_lookup->get_size();
		$label = (string)( $flair['label'] ?? $flair['group'] );

		return Html::rawElement( 'span', [
			'class' => $classes,
			'data-uf-group' => $flair['group'],
			'style' => '--uf-flair-size:' . $size . 'px',
		], Html::element( 'img', [
			'class' => 'uf-flair__image',
			'src' => $flair['url'],
			'width' => (int)$flair['width'],
			'height' => (int)$flair['height'],
			'alt' => $label,
		] ) );
	}

}
