/**
 * Promptor Toast Notification System
 *
 * Replaces native alert() calls with non-blocking toast notifications.
 *
 * Usage:
 *   PromptorToast.success( 'Title', 'Message' );
 *   PromptorToast.error( 'Title', 'Message' );
 *   PromptorToast.warning( 'Title', 'Message' );
 *   PromptorToast.info( 'Title', 'Message' );
 *   PromptorToast.show( message );          // shorthand — info type
 *   PromptorToast.show( message, 'error' ); // shorthand with type
 *
 * @package Promptor
 * @since   1.3.0
 */

/* global jQuery */
( function ( $ ) {
	'use strict';

	var CONTAINER_ID = 'promptor-toast-container';
	var DEFAULT_DURATION = 5000; // 5 seconds

	var ICONS = {
		success: 'dashicons-yes-alt',
		error: 'dashicons-dismiss',
		warning: 'dashicons-warning',
		info: 'dashicons-info',
	};

	/**
	 * Ensure the toast container exists in the DOM.
	 */
	function ensureContainer() {
		if ( document.getElementById( CONTAINER_ID ) ) {
			return;
		}
		var container = document.createElement( 'div' );
		container.id = CONTAINER_ID;
		container.className = 'promptor-toast-container';
		document.body.appendChild( container );
	}

	/**
	 * Create and display a toast notification.
	 *
	 * @param {Object} options
	 * @param {string} options.type     success | error | warning | info
	 * @param {string} options.title    Bold title text (optional).
	 * @param {string} options.message  Body text.
	 * @param {number} options.duration Auto-dismiss ms (0 = manual only).
	 */
	function createToast( options ) {
		ensureContainer();

		var type = options.type || 'info';
		var duration = typeof options.duration !== 'undefined'
			? options.duration
			: ( type === 'error' ? 8000 : DEFAULT_DURATION );

		var toast = document.createElement( 'div' );
		toast.className = 'promptor-toast toast-' + type;

		// Icon
		var icon = document.createElement( 'span' );
		icon.className = 'toast-icon dashicons ' + ( ICONS[ type ] || ICONS.info );
		toast.appendChild( icon );

		// Content
		var content = document.createElement( 'div' );
		content.className = 'toast-content';

		if ( options.title ) {
			var title = document.createElement( 'div' );
			title.className = 'toast-title';
			title.textContent = options.title;
			content.appendChild( title );
		}

		var message = document.createElement( 'div' );
		message.className = 'toast-message';
		message.textContent = options.message || '';
		content.appendChild( message );
		toast.appendChild( content );

		// Close button
		var closeBtn = document.createElement( 'button' );
		closeBtn.className = 'toast-close';
		closeBtn.innerHTML = '&times;';
		closeBtn.setAttribute( 'type', 'button' );
		closeBtn.addEventListener( 'click', function () {
			dismissToast( toast );
		} );
		toast.appendChild( closeBtn );

		// Append
		document.getElementById( CONTAINER_ID ).appendChild( toast );

		// Auto-dismiss
		if ( duration > 0 ) {
			setTimeout( function () {
				dismissToast( toast );
			}, duration );
		}

		return toast;
	}

	/**
	 * Dismiss a toast with animation.
	 */
	function dismissToast( toast ) {
		if ( ! toast || toast.classList.contains( 'toast-out' ) ) {
			return;
		}
		toast.classList.add( 'toast-out' );
		setTimeout( function () {
			if ( toast.parentNode ) {
				toast.parentNode.removeChild( toast );
			}
		}, 350 );
	}

	// Public API
	window.PromptorToast = {
		show: function ( message, type ) {
			return createToast( {
				type: type || 'info',
				message: message,
			} );
		},

		success: function ( title, message ) {
			return createToast( { type: 'success', title: title, message: message } );
		},

		error: function ( title, message ) {
			return createToast( { type: 'error', title: title, message: message } );
		},

		warning: function ( title, message ) {
			return createToast( { type: 'warning', title: title, message: message } );
		},

		info: function ( title, message ) {
			return createToast( { type: 'info', title: title, message: message } );
		},
	};

} )( jQuery );
