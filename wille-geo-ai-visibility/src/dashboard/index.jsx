/**
 * Entry point: mounts the dashboard app onto the admin page.
 * React and Recharts are bundled (IIFE, nothing leaks to window),
 * so there is no dependency on other plugins' scripts.
 */
import { createRoot } from 'react-dom/client';
import App from './App';
import AuditApp from './audit';

function mount() {
	const dashEl = document.getElementById( 'geoins-dashboard-root' );
	if ( dashEl && window.geoinsDash ) {
		createRoot( dashEl ).render( <App config={ window.geoinsDash } /> );
	}
	const auditEl = document.getElementById( 'geoins-audit-root' );
	if ( auditEl && window.geoinsDash ) {
		createRoot( auditEl ).render( <AuditApp config={ window.geoinsDash } /> );
	}
}

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', mount );
} else {
	mount();
}
