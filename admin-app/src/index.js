import { createRoot } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import App from './App';

// Check whether WordPress provided the REST API settings.
if ( ! window.sdfSettings ) {
    console.error( 'WordPress REST API settings are missing.' );
} else {

    // Configure the REST API security nonce.
    apiFetch.use(
        apiFetch.createNonceMiddleware( window.sdfSettings.nonce )
    );

    // Configure the REST API base URL.
    apiFetch.use(
        apiFetch.createRootURLMiddleware( window.sdfSettings.root )
    );

    // Find the React root element in the WordPress admin page.
    const container = document.getElementById( 'sdf-react-root' );

    if ( container ) {

        // Create the React root.
        const root = createRoot( container );

        // Render the App component.
        root.render( <App /> );
    }
}