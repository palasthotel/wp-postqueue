import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import QueueEditor from './components/QueueEditor.jsx';

domReady( () => {
	const container = document.getElementById( 'post-queue-editor' );
	if ( ! container ) {
		return;
	}

	// createRoot, not ReactDOM.render: the legacy entry point warns under React 18 and is
	// gone in React 19, which newer WordPress versions ship.
	createRoot( container ).render( <QueueEditor /> );
} );
