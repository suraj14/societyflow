/**
 * Authentication Cache Clearing - FIXED
 * Ensures login page is never cached without causing redirect loops
 */

(function() {
    'use strict';

    // Only run on login page
    if (!window.location.pathname.includes('/login')) {
        return;
    }

    console.log('🔐 Login page detected - setting up cache prevention');

    // Clear browser storage on page load
    clearBrowserStorage();

    // Add no-cache headers via meta tags
    addNoCacheHeaders();

    function clearBrowserStorage() {
        try {
            localStorage.clear();
            console.log('✓ localStorage cleared');
        } catch (e) {
            console.warn('Could not clear localStorage:', e);
        }

        try {
            sessionStorage.clear();
            console.log('✓ sessionStorage cleared');
        } catch (e) {
            console.warn('Could not clear sessionStorage:', e);
        }

        // Clear service worker cache
        if ('caches' in window) {
            caches.keys().then(function(cacheNames) {
                cacheNames.forEach(function(cacheName) {
                    caches.delete(cacheName);
                });
                console.log('✓ Service worker cache cleared');
            });
        }
    }

    function addNoCacheHeaders() {
        // Check if headers already added
        if (document.querySelector('meta[http-equiv="Cache-Control"]')) {
            return;
        }

        const noCacheHeaders = [
            { httpEquiv: 'Cache-Control', content: 'no-store, no-cache, must-revalidate, max-age=0, private' },
            { httpEquiv: 'Pragma', content: 'no-cache' },
            { httpEquiv: 'Expires', content: 'Thu, 01 Jan 1970 00:00:00 GMT' }
        ];

        noCacheHeaders.forEach(header => {
            const meta = document.createElement('meta');
            meta.httpEquiv = header.httpEquiv;
            meta.content = header.content;
            document.head.appendChild(meta);
        });

        console.log('✓ No-cache headers added');
    }

    // Listen for logout form submission
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (form.action && form.action.includes('/logout')) {
            console.log('🔐 Logout form detected - clearing cache');
            clearBrowserStorage();
        }
    });
})();
