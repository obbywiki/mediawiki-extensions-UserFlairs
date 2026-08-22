'use strict';

var ICON_SELECTOR = '.citizen-userMenu .citizen-dropdown-summary > .mw-ui-icon-wikimedia-userAvatar';

function run() {
	var cfg = mw.config.get( 'wgUserFlairsHeader' );
	if ( !cfg || !cfg.url ) { return; }

	var icon = document.querySelector( ICON_SELECTOR );
	if ( !icon || !icon.parentNode ) { return; }

	var summary = icon.parentNode;
	if ( summary.querySelector( '.uf-flair--header' ) ) { return; }

	var img = document.createElement( 'img' );
	img.className = 'uf-flair uf-flair--header';
	img.src = cfg.url;
	img.alt = cfg.label || '';
	img.width = 12;
	img.height = 12;

	if ( cfg.group ) {
		img.setAttribute( 'data-uf-group', cfg.group );
	}
	
	summary.appendChild( img );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', run );
} else {
	run();
}