( function () {
	'use strict';

	var editor = document.querySelector( '[data-navigation-editor]' );
	if ( ! editor ) {
		return;
	}

	var list = editor.querySelector( '[data-navigation-items]' );
	var template = editor.querySelector( '[data-navigation-item-template]' );
	var status = editor.querySelector( '[data-navigation-status]' );
	var messages = window.twentyThreeNavigation || {};

	function items() {
		return Array.prototype.slice.call( list.querySelectorAll( ':scope > [data-navigation-item]' ) );
	}

	function depth( item ) {
		return parseInt( item.querySelector( '[data-field="depth"]' ).value, 10 ) || 0;
	}

	function setDepth( item, value ) {
		item.querySelector( '[data-field="depth"]' ).value = value ? '1' : '0';
		item.classList.toggle( 'is-child', Boolean( value ) );
	}

	function isPreserved( item ) {
		return '1' === item.getAttribute( 'data-preserved' );
	}

	function announce( message ) {
		status.textContent = '';
		window.setTimeout( function () {
			status.textContent = message || '';
		}, 20 );
	}

	function topLevelBefore( allItems, index ) {
		for ( var cursor = index - 1; cursor >= 0; cursor-- ) {
			if ( 0 === depth( allItems[ cursor ] ) ) {
				return allItems[ cursor ];
			}
		}
		return null;
	}

	function branch( allItems, index ) {
		var result = [ allItems[ index ] ];
		if ( 0 === depth( allItems[ index ] ) ) {
			for ( var cursor = index + 1; cursor < allItems.length && 1 === depth( allItems[ cursor ] ); cursor++ ) {
				result.push( allItems[ cursor ] );
			}
		}
		return result;
	}

	function refreshTypeFields( item ) {
		if ( isPreserved( item ) ) {
			return;
		}

		var type = item.querySelector( '[data-field="type"]' ).value;
		Array.prototype.forEach.call( item.querySelectorAll( '[data-type-field]' ), function ( field ) {
			field.hidden = field.getAttribute( 'data-type-field' ) !== type;
		} );
	}

	function refresh() {
		var allItems = items();

		allItems.forEach( function ( item, index ) {
			if ( 0 === index || isPreserved( item ) ) {
				setDepth( item, 0 );
			}

			if ( 1 === depth( item ) ) {
				var parent = topLevelBefore( allItems, index );
				if ( ! parent || isPreserved( parent ) ) {
					setDepth( item, 0 );
				}
			}

			Array.prototype.forEach.call( item.querySelectorAll( '[name]' ), function ( field ) {
				field.name = field.name.replace( /items\[[^\]]+\]/, 'items[' + index + ']' );
			} );

			Array.prototype.forEach.call( item.querySelectorAll( '[id]' ), function ( field ) {
				field.id = field.id.replace( /twentythree-navigation-item-(?:__INDEX__|\d+)-/, 'twentythree-navigation-item-' + index + '-' );
			} );

			Array.prototype.forEach.call( item.querySelectorAll( 'label[for]' ), function ( label ) {
				label.htmlFor = label.htmlFor.replace( /twentythree-navigation-item-(?:__INDEX__|\d+)-/, 'twentythree-navigation-item-' + index + '-' );
			} );

			var up = item.querySelector( '[data-move-up]' );
			var down = item.querySelector( '[data-move-down]' );
			var indent = item.querySelector( '[data-indent]' );
			var outdent = item.querySelector( '[data-outdent]' );

			if ( up ) {
				up.disabled = 0 === index || ( 1 === depth( item ) && 0 === depth( allItems[ index - 1 ] ) );
			}
			if ( down ) {
				down.disabled = index === allItems.length - 1 || ( 1 === depth( item ) && 0 === depth( allItems[ index + 1 ] ) );
			}
			if ( indent ) {
				var prospectiveParent = topLevelBefore( allItems, index );
				var hasChildren = index + 1 < allItems.length && 1 === depth( allItems[ index + 1 ] );
				indent.disabled = 1 === depth( item ) || ! prospectiveParent || isPreserved( prospectiveParent ) || hasChildren;
			}
			if ( outdent ) {
				outdent.disabled = 0 === depth( item );
			}

			refreshTypeFields( item );
		} );
	}

	function moveUp( item ) {
		var allItems = items();
		var index = allItems.indexOf( item );
		if ( index <= 0 ) {
			return;
		}

		if ( 1 === depth( item ) ) {
			if ( 1 === depth( allItems[ index - 1 ] ) ) {
				list.insertBefore( item, allItems[ index - 1 ] );
			}
			return;
		}

		var previous = topLevelBefore( allItems, index );
		if ( previous ) {
			list.insertBefore( item, previous );
			branch( allItems, index ).slice( 1 ).forEach( function ( child ) {
				list.insertBefore( child, previous );
			} );
		}
	}

	function moveDown( item ) {
		var allItems = items();
		var index = allItems.indexOf( item );
		if ( index < 0 || index >= allItems.length - 1 ) {
			return;
		}

		if ( 1 === depth( item ) ) {
			if ( 1 === depth( allItems[ index + 1 ] ) ) {
				list.insertBefore( allItems[ index + 1 ], item );
			}
			return;
		}

		var currentBranch = branch( allItems, index );
		var nextIndex = index + currentBranch.length;
		if ( nextIndex >= allItems.length ) {
			return;
		}
		var nextBranch = branch( allItems, nextIndex );
		var reference = nextBranch[ nextBranch.length - 1 ].nextSibling;
		currentBranch.forEach( function ( branchItem ) {
			list.insertBefore( branchItem, reference );
		} );
	}

	list.addEventListener( 'change', function ( event ) {
		if ( event.target.matches( '[data-field="type"]' ) ) {
			refreshTypeFields( event.target.closest( '[data-navigation-item]' ) );
		}
	} );

	list.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( 'button' );
		var item = event.target.closest( '[data-navigation-item]' );
		if ( ! button || ! item ) {
			return;
		}

		if ( button.matches( '[data-move-up]' ) ) {
			moveUp( item );
			announce( messages.moved );
		} else if ( button.matches( '[data-move-down]' ) ) {
			moveDown( item );
			announce( messages.moved );
		} else if ( button.matches( '[data-indent]' ) ) {
			setDepth( item, 1 );
			announce( messages.indented );
		} else if ( button.matches( '[data-outdent]' ) ) {
			setDepth( item, 0 );
			announce( messages.outdented );
		} else if ( button.matches( '[data-remove]' ) ) {
			var allItems = items();
			var index = allItems.indexOf( item );
			var focusItem = item.nextElementSibling || item.previousElementSibling;
			if ( 0 === depth( item ) ) {
				for ( var cursor = index + 1; cursor < allItems.length && 1 === depth( allItems[ cursor ] ); cursor++ ) {
					setDepth( allItems[ cursor ], 0 );
				}
			}
			item.remove();
			if ( focusItem ) {
				var focusControl = focusItem.querySelector( '[data-field="label"], button' );
				if ( focusControl ) {
					focusControl.focus();
				}
			} else {
				editor.querySelector( '[data-add-navigation-item]' ).focus();
			}
			announce( messages.removed );
		}

		refresh();
	} );

	editor.querySelector( '[data-add-navigation-item]' ).addEventListener( 'click', function () {
		var index = items().length;
		var wrapper = document.createElement( 'div' );
		wrapper.innerHTML = template.innerHTML.replace( /__INDEX__/g, String( index ) ).trim();
		var item = wrapper.firstElementChild;
		list.appendChild( item );
		refresh();
		item.querySelector( '[data-field="label"]' ).focus();
		announce( messages.added );
	} );

	refresh();
}() );
