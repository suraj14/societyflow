/**
 * SMART CACHE BUSTER
 * Fixes browser cache issues while allowing redirects and auth pages to work
 * This script MUST be loaded FIRST before any other scripts
 */

(function() {
    'use strict';

    console.log('🔥 Cache Buster: Initializing smart cache busting...');

    // ============================================================================
    // DETECT CURRENT PAGE TYPE
    // ============================================================================
    
    const currentUrl = window.location.href;
    const isAuthPage = currentUrl.includes('/login') || currentUrl.includes('/register') || currentUrl.includes('/forgot-password');
    const isLogoutPage = currentUrl.includes('/logout');
    
    console.log('🔥 Cache Buster: Current URL:', currentUrl);
    console.log('🔥 Cache Buster: Is Auth Page:', isAuthPage);

    // ============================================================================
    // CLEAR SERVICE WORKER CACHE (SAFE)
    // ============================================================================

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistrations().then(registrations => {
            registrations.forEach(registration => {
                registration.unregister();
                console.log('🔥 Cache Buster: Service worker unregistered');
            });
        });
    }

    // ============================================================================
    // SMART STORAGE CLEARING
    // ============================================================================

    try {
        // Only clear cache-related storage, not all storage
        const keysToRemove = [];
        for (let key in localStorage) {
            if (localStorage.hasOwnProperty(key)) {
                if (key.startsWith('cache_') || key.startsWith('__') || key === 'persist:root') {
                    keysToRemove.push(key);
                }
            }
        }
        keysToRemove.forEach(key => localStorage.removeItem(key));
        
        console.log('🔥 Cache Buster: Cache storage cleared');
    } catch (e) {
        console.warn('Cache Buster: Could not clear storage:', e);
    }

    // ============================================================================
    // ADD META TAGS FOR CACHE PREVENTION
    // ============================================================================

    document.addEventListener('DOMContentLoaded', function() {
        // Add meta tags to prevent caching
        const noCacheMeta = document.createElement('meta');
        noCacheMeta.httpEquiv = 'Cache-Control';
        noCacheMeta.content = 'no-store, no-cache, must-revalidate, max-age=0';
        document.head.appendChild(noCacheMeta);

        const pragmaMeta = document.createElement('meta');
        pragmaMeta.httpEquiv = 'Pragma';
        pragmaMeta.content = 'no-cache';
        document.head.appendChild(pragmaMeta);

        const expiresMeta = document.createElement('meta');
        expiresMeta.httpEquiv = 'Expires';
        expiresMeta.content = '0';
        document.head.appendChild(expiresMeta);

        console.log('🔥 Cache Buster: Meta tags added');
    });

    // ============================================================================
    // STORE PAGE LOAD TIMESTAMP
    // ============================================================================

    window.pageLoadTime = Date.now();
    window.lastDataRefresh = Date.now();

    // ============================================================================
    // SMART AJAX CACHE BUSTING (ONLY FOR DATA REQUESTS)
    // ============================================================================

    // Override fetch to add cache-busting parameters
    const originalFetch = window.fetch;
    window.fetch = function(...args) {
        let url = args[0];
        const options = args[1] || {};

        // Only add cache-bust for API/data requests, not for page navigation
        if (typeof url === 'string') {
            const isApiRequest = url.includes('/api/') || url.includes('?');
            const isPageNavigation = url.includes('/login') || url.includes('/logout') || url.includes('/register') || url.includes('/dashboard');
            
            if (isApiRequest && !isPageNavigation && (!options.method || options.method === 'GET')) {
                const separator = url.includes('?') ? '&' : '?';
                url = url + separator + '_cache_bust=' + Date.now();
            }
        }

        // Add cache-busting headers for API requests only
        if (!options.headers) {
            options.headers = {};
        }
        
        if (typeof url === 'string' && url.includes('/api/')) {
            options.headers['Cache-Control'] = 'no-store, no-cache, must-revalidate';
            options.headers['Pragma'] = 'no-cache';
        }
        
        options.headers['X-Requested-With'] = 'XMLHttpRequest';

        return originalFetch.apply(this, [url, options]);
    };

    console.log('🔥 Cache Buster: Smart fetch interceptor installed');

    // ============================================================================
    // JQUERY AJAX CACHE BUSTING
    // ============================================================================

    if (typeof jQuery !== 'undefined') {
        jQuery.ajaxSetup({
            cache: false,
            headers: {
                'Cache-Control': 'no-store, no-cache, must-revalidate',
                'Pragma': 'no-cache'
            }
        });

        console.log('🔥 Cache Buster: jQuery AJAX cache busting enabled');
    }

    // ============================================================================
    // AXIOS CACHE BUSTING
    // ============================================================================

    if (typeof axios !== 'undefined') {
        axios.defaults.headers.common['Cache-Control'] = 'no-store, no-cache, must-revalidate';
        axios.defaults.headers.common['Pragma'] = 'no-cache';

        // Add cache-busting interceptor for API requests only
        axios.interceptors.request.use(function(config) {
            if ((config.method === 'get' || config.method === 'GET') && config.url.includes('/api/')) {
                config.params = config.params || {};
                config.params._cache_bust = Date.now();
            }
            return config;
        });

        console.log('🔥 Cache Buster: Axios cache busting enabled');
    }

    // ============================================================================
    // PREVENT BACK BUTTON CACHE (SMART)
    // ============================================================================

    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            console.log('🔥 Cache Buster: Page restored from cache');
            // Only reload if not on auth pages
            if (!isAuthPage && !isLogoutPage) {
                location.reload(true);
            }
        }
    });

    // ============================================================================
    // FORM SUBMISSION CACHE BUSTING
    // ============================================================================

    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (form && form.method && form.method.toUpperCase() === 'POST') {
            // Don't add cache bust to logout forms - let them redirect normally
            if (!form.action.includes('/logout')) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = '_cache_bust';
                input.value = Date.now();
                form.appendChild(input);
            }
        }
    });

    // ============================================================================
    // PERIODIC CACHE CLEANUP
    // ============================================================================

    setInterval(function() {
        try {
            const keysToRemove = [];
            for (let key in localStorage) {
                if (localStorage.hasOwnProperty(key) && (key.startsWith('cache_') || key.startsWith('__'))) {
                    keysToRemove.push(key);
                }
            }
            keysToRemove.forEach(key => localStorage.removeItem(key));
        } catch (e) {
            console.warn('Cache Buster: Could not cleanup cache:', e);
        }
    }, 300000); // 5 minutes

    // ============================================================================
    // INITIALIZATION COMPLETE
    // ============================================================================

    console.log('✅ Cache Buster: Smart cache busting ACTIVE');
    console.log('✅ API requests will include cache-busting parameters');
    console.log('✅ Auth pages and redirects work normally');
    console.log('✅ Fresh data will be loaded on every request');

})();
