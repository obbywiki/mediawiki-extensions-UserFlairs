'use strict';

var list = document.getElementById( 'uf-order-list' );
if ( !list ) { return; }

var form = document.getElementById( 'userflairs-order' );
var order_input = document.getElementById( 'uf-order-field' ) || ( form ? form.querySelector( 'input[name="wporder"]' ) : null );
var banner = document.getElementById( 'uf-manage-unsaved' );
var live = document.getElementById( 'uf-manage-live' );
var original = serialize();


// thank you github

var THRESHOLD_PX = 5;
var FLIP_MS = 200;
var SCROLL_EDGE = 56;
var SCROLL_STEP = 14;

var pending = null;
var drag = null;
var last_x = 0;
var last_y = 0;
var scroll_raf = 0;
var flip_gen = 0;

var reduce_motion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

function assigned_items() {
	return list.querySelectorAll( '.uf-manage__item--assigned' );
}

function serialize() {
	var groups = [];
	assigned_items().forEach( function ( item ) {
		groups.push( item.getAttribute( 'data-group' ) );
	} );

	return groups.join( '|' );
}

function mark_dirty() {
	var next = serialize();
	if ( order_input ) {
		order_input.value = next;
	}

	if ( banner ) {
		banner.hidden = next === original;
	}

	if ( form ) {
		form.classList.toggle( 'uf-manage__form--dirty', next !== original );
	}
}

function item_label( item ) {
	var label = item.querySelector( '.uf-manage__label' );
	return label ? label.textContent.trim() : ( item.getAttribute( 'data-group' ) || '' );
}

function update_ranks() {
	var nodes = assigned_items();
	var total = nodes.length;

	nodes.forEach( function ( item, index ) {
		var handle = item.querySelector( '.uf-manage__handle' );
		if ( handle ) {
			handle.setAttribute(
				'aria-label',
				mw.msg( 'userflairs-drag-handle' ) + ', ' + mw.msg( 'userflairs-position', index + 1, total )
			);
		}
	} );
}

function announce( item ) {
	if ( !live ) { return; }

	var index = Array.prototype.indexOf.call( assigned_items(), item ) + 1;
	live.textContent = mw.msg( 'userflairs-moved', item_label( item ), index );
}

function after_reorder( item, speak ) {
	update_ranks();
	mark_dirty();

	if ( speak ) {
		announce( item );
	}
}

function flip( mutate ) {
	if ( reduce_motion ) {
		mutate();
		return;
	}

	var nodes = [];
	var first = [];
	assigned_items().forEach( function ( el ) {
		nodes.push( el );
		first.push( el.getBoundingClientRect() );
	} );
	mutate();
	list.classList.add( 'uf-manage__list--flipping' );
	nodes.forEach( function ( el, i ) {
		var last = el.getBoundingClientRect();
		var dy = first[ i ].top - last.top;

		if ( Math.abs( dy ) < 1 ) { return; }

		el.style.transition = 'none';
		el.style.transform = 'translateY(' + dy + 'px)';
		el.getBoundingClientRect();
		el.style.transition = '';
		el.style.transform = '';
	} );
}

function position_ghost( x, y ) {
	if ( !drag || !drag.ghost ) {
		return;
	}
	var scale = reduce_motion ? '' : ' scale(1.02)';
	drag.ghost.style.transform = 'translate(' + ( x - drag.offset_x ) + 'px, ' + ( y - drag.offset_y ) + 'px)' + scale;
}

function insertion_before( client_y ) {
	var origin = list.getBoundingClientRect().top;
	var nodes = assigned_items();
	var i, el, top, height;

	for ( i = 0; i < nodes.length; i++ ) {
		el = nodes[ i ];
		if ( drag && el === drag.item ) { continue; }

		top = origin + el.offsetTop;
		height = el.offsetHeight;
		if ( client_y < top + height / 2 ) {
			return el;
		}
	}
	return null;
}

function maybe_reorder( client_y ) {
	if ( !drag ) { return; }

	var before = insertion_before( client_y );
	var item = drag.item;

	if ( before && item.nextElementSibling === before ) { return; }
	if ( !before && item === list.lastElementChild ) { return; }

	flip( function () {
		if ( before ) {
			list.insertBefore( item, before );
		} else {
			list.appendChild( item );
		}
	} );
	update_ranks();
	mark_dirty();
}

function scroll_tick() {
	if ( !drag ) {
		scroll_raf = 0;
		return;
	}

	var dir = 0;
	if ( last_y < SCROLL_EDGE ) {
		dir = -SCROLL_STEP;
	} else if ( last_y > window.innerHeight - SCROLL_EDGE ) {
		dir = SCROLL_STEP;
	}

	if ( dir ) {
		window.scrollBy( 0, dir );
		position_ghost( last_x, last_y );
		maybe_reorder( last_y );
	}

	scroll_raf = window.requestAnimationFrame( scroll_tick );
}

function start_drag( event ) {
	var item = pending.item;
	var rect = item.getBoundingClientRect();
	var ghost = item.cloneNode( true );
	var ghost_handle = ghost.querySelector( '.uf-manage__handle' );
	var scale = reduce_motion ? '' : ' scale(1.02)';

	flip_gen++;
	ghost.classList.add( 'uf-manage__item--ghost' );
	ghost.classList.remove( 'uf-manage__item--slot' );
	ghost.removeAttribute( 'data-group' );
	ghost.setAttribute( 'aria-hidden', 'true' );

	if ( ghost_handle ) {
		ghost_handle.tabIndex = -1;
		ghost_handle.disabled = true;
	}

	ghost.style.width = rect.width + 'px';
	ghost.style.height = rect.height + 'px';
	ghost.style.transform = 'translate(' + rect.left + 'px, ' + rect.top + 'px)' + scale;
	document.body.appendChild( ghost );

	item.classList.add( 'uf-manage__item--slot' );
	item.setAttribute( 'aria-hidden', 'true' );

	document.documentElement.classList.add( 'uf-is-reordering' );
	list.classList.add( 'uf-manage__list--flipping' );

	drag = {
		item: item,
		ghost: ghost,
		offset_x: pending.x - rect.left,
		offset_y: pending.y - rect.top,
		pointer_id: pending.pointer_id,
		start_order: Array.prototype.slice.call( list.children ),
		handle: pending.handle
	};
	pending = null;

	last_x = event.clientX;
	last_y = event.clientY;
	position_ghost( last_x, last_y );
	if ( !scroll_raf ) {
		scroll_raf = window.requestAnimationFrame( scroll_tick );
	}
}

function release_capture( handle, pointer_id ) {
	if ( !handle || !handle.releasePointerCapture ) { return; }
	try { handle.releasePointerCapture( pointer_id ); } catch ( e ) {}
}

function teardown_drag( cancelled ) {
	var item, handle, pointer_id, gen;

	if ( !drag ) { pending = null; return; }

	item = drag.item;
	handle = drag.handle;
	pointer_id = drag.pointer_id;

	if ( cancelled && drag.start_order ) {
		drag.start_order.forEach( function ( node ) {
			list.appendChild( node );
		} );
	}

	if ( drag.ghost && drag.ghost.parentNode ) {
		drag.ghost.parentNode.removeChild( drag.ghost );
	}

	item.classList.remove( 'uf-manage__item--slot' );
	item.removeAttribute( 'aria-hidden' );
	document.documentElement.classList.remove( 'uf-is-reordering' );
	release_capture( handle, pointer_id );
	drag = null;
	pending = null;

	gen = flip_gen;
	window.setTimeout( function () {
		if ( gen === flip_gen && !drag ) {
			list.classList.remove( 'uf-manage__list--flipping' );
		}
	}, FLIP_MS + 20 );

	after_reorder( item, !cancelled );
	if ( handle ) {
		handle.focus();
	}
}

function on_pointer_down( event ) {
	var handle;
	var item;
	if ( event.button != null && event.button !== 0 ) { return; }

	handle = event.target.closest( '.uf-manage__handle' );
	if ( !handle || !list.contains( handle ) ) { return; }

	item = handle.closest( '.uf-manage__item--assigned' );
	if ( !item ) { return; }

	pending = { item: item, handle: handle, x: event.clientX, y: event.clientY, pointer_id: event.pointerId };

	if ( handle.setPointerCapture ) {
		handle.setPointerCapture( event.pointerId );
	}
	handle.focus();
	event.preventDefault();
}

function on_pointer_move( event ) {
	var dx;
	var dy;

	if ( pending ) {
		dx = event.clientX - pending.x;
		dy = event.clientY - pending.y;
		if ( ( dx * dx + dy * dy ) < THRESHOLD_PX * THRESHOLD_PX ) { return; }
		start_drag( event );
	}
	if ( !drag ) { return; }

	last_x = event.clientX;
	last_y = event.clientY;
	position_ghost( last_x, last_y );
	maybe_reorder( last_y );
	event.preventDefault();
}

function on_pointer_up( event ) {
	if ( pending && event.pointerId === pending.pointer_id ) {
		release_capture( pending.handle, pending.pointer_id );
		pending = null;
		return;
	}
	if ( drag && event.pointerId === drag.pointer_id ) {
		teardown_drag( false );
		event.preventDefault();
	}
}

function on_pointer_cancel() {
	if ( pending ) {
		release_capture( pending.handle, pending.pointer_id );
		pending = null;
	}

	if ( drag ) {
		teardown_drag( true );
	}
}

function on_keydown( event ) {
	var handle;
	var item;
	if ( event.key === 'Escape' && drag ) {
		event.preventDefault();
		teardown_drag( true );
		return;
	}

	if ( drag ) { return; }
	if ( event.key !== 'ArrowUp' && event.key !== 'ArrowDown' ) { return; }

	handle = event.target.closest( '.uf-manage__handle' );
	if ( !handle || !list.contains( handle ) ) { return; }

	item = handle.closest( '.uf-manage__item--assigned' );
	if ( !item ) { return; }

	if ( event.key === 'ArrowUp' ) {
		if ( !item.previousElementSibling ) { return; }

		event.preventDefault();
		flip( function () {
			list.insertBefore( item, item.previousElementSibling );
		} );

		after_reorder( item, true );
		handle.focus();

		return;
	}

	if ( !item.nextElementSibling ) { return; }

	event.preventDefault();
	flip( function () {
		list.insertBefore( item.nextElementSibling, item );
	} );
	after_reorder( item, true );
	handle.focus();
}

function place_notice() {
	if ( !form || !banner ) { return; }

	var buttons = form.querySelector( '.mw-htmlform-submit-buttons' );
	if ( buttons && banner.parentNode !== buttons ) {
		buttons.appendChild( banner );
	}
}

place_notice();

list.addEventListener( 'pointerdown', on_pointer_down );
document.addEventListener( 'keydown', on_keydown );
document.addEventListener( 'pointermove', on_pointer_move );
document.addEventListener( 'pointerup', on_pointer_up );
document.addEventListener( 'pointercancel', on_pointer_cancel );
