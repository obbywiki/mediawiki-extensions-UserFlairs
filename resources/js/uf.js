'use strict';

var EDIT_SELECTOR = '.profile-avatar-edit-button, .profile-avatar-edit-action';

function mark_edit( avatar, flair ) {
	if ( !avatar || !flair || !avatar.classList.contains( 'profile-avatar' ) ) { return; }

	if ( avatar.querySelector( EDIT_SELECTOR ) ) {
		avatar.classList.add( 'uf-has-avatar-edit' );
		flair.classList.add( 'uf-flair--above-edit' );
	} else {
		flair.classList.add( 'uf-flair--on-portrait' );
	}
}

function place_relocatable() {
	var flair = document.querySelector( '.uf-flair--relocatable' );
	if ( !flair ) { return false; }

	var avatar = document.querySelector( '.ip-avatar, .profile-avatar' );
	if ( !avatar ) { return false; }

	avatar.appendChild( flair );
	flair.classList.remove( 'uf-flair--relocatable' );

	mark_edit( avatar, flair );

	return true;
}

function inject_from_config() {
	if ( document.querySelector( '.uf-flair' ) ) { return; }

	var cfg = mw.config.get( 'wgUserFlairs' );
	if ( !cfg || !cfg.url ) { return; }

	var avatar = document.querySelector( '.ip-avatar, .profile-avatar' );
	if ( !avatar ) { return; }

	var wrap = document.createElement( 'span' );
	wrap.className = 'uf-flair';

	if ( cfg.group ) {
		wrap.setAttribute( 'data-uf-group', cfg.group );
	}

	if ( cfg.width || cfg.height ) {
		wrap.style.setProperty( '--uf-flair-size', ( cfg.width || cfg.height ) + 'px' );
	}

	var img = document.createElement( 'img' );
	img.className = 'uf-flair__image';
	img.src = cfg.url;
	img.width = cfg.width || 32;
	img.height = cfg.height || 32;
	img.alt = cfg.label || '';

	wrap.appendChild( img );
	avatar.appendChild( wrap );
	mark_edit( avatar, wrap );
}

function run() {
	if ( !place_relocatable() ) {
		inject_from_config();
	}
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', run );
} else {
	run();
}
