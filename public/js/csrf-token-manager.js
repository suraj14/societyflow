/**
 * CSRF Token Manager - Permanent 419 Fix
 * Handles automatic token refresh and injection
 */

class CsrfTokenManager {
    constructor() {
        this.tokenRefreshInterval = 15 * 60 * 1000; // 15 minutes
        this.lastRefreshTime = Date.now();
        this.isRefreshing = false;
        this.init();
    }

    /**
     * Initialize the CSRF token manager
     */
    init() {
        console.log('🔐 CSRF Token Manager: Initializing...');
        
        // Get initial token
        this.updateToken();
        
        // Setup periodic refresh
        this.setupPeriodicRefresh();
        
        // Setup page visibility detection
        this.setupVisibilityDetection();
        
        // Setup form interception
        this.setupFormInterception();
        
        // Setup AJAX interceptor
        this.setupAjaxInterceptor();
        
        console.log('✅ CSRF Token Manager: Ready');
    }

    /**
     * Get current CSRF token from meta tag
     */
    getToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    /**
     * Update global token reference
     */
    updateToken() {
        const token = this.getToken();
        window.csrfToken = token;
        
        // Update all meta tags
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        if (metaTag) {
            metaTag.setAttribute('content', token);
        }
        
        return token;
    }

    /**
     * Refresh CSRF token from server
     */
    async refreshToken() {
        if (this.isRefreshing) {
            console.log('⏳ Token refresh already in progress');
            return;
        }

        this.isRefreshing = true;
        
        try {
            const response = await fetch(window.location.href, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'text/html'
                }
            });

            if (response.ok) {
                const html = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newToken = doc.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                
                if (newToken) {
                    const metaTag = document.querySelector('meta[name="csrf-token"]');
                    if (metaTag) {
                        metaTag.setAttribute('content', newToken);
                    }
                    window.csrfToken = newToken;
                    this.lastRefreshTime = Date.now();
                    console.log('✅ CSRF token refreshed successfully');
                }
            }
        } catch (error) {
            console.warn('⚠️ Token refresh failed:', error.message);
        } finally {
            this.isRefreshing = false;
        }
    }

    /**
     * Setup periodic token refresh
     */
    setupPeriodicRefresh() {
        setInterval(() => {
            const timeSinceRefresh = Date.now() - this.lastRefreshTime;
            if (timeSinceRefresh > this.tokenRefreshInterval) {
                console.log('🔄 Periodic CSRF token refresh');
                this.refreshToken();
            }
        }, 5 * 60 * 1000); // Check every 5 minutes
    }

    /**
     * Setup page visibility detection
     */
    setupVisibilityDetection() {
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                console.log('📱 Page became visible - refreshing CSRF token');
                this.refreshToken();
            }
        });

        // Also refresh on focus
        window.addEventListener('focus', () => {
            const timeSinceRefresh = Date.now() - this.lastRefreshTime;
            if (timeSinceRefresh > 10 * 60 * 1000) { // 10 minutes
                console.log('🔄 Window focused - refreshing CSRF token');
                this.refreshToken();
            }
        });
    }

    /**
     * Setup form interception to inject token
     */
    setupFormInterception() {
        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (form.tagName !== 'FORM') return;

            // Ensure token is in form
            let tokenInput = form.querySelector('input[name="_token"]');
            if (!tokenInput) {
                tokenInput = document.createElement('input');
                tokenInput.type = 'hidden';
                tokenInput.name = '_token';
                form.appendChild(tokenInput);
            }

            // Update token value
            tokenInput.value = this.getToken();
            console.log('✅ CSRF token injected into form');
        });
    }

    /**
     * Setup AJAX interceptor
     */
    setupAjaxInterceptor() {
        // Intercept fetch requests
        const originalFetch = window.fetch;
        window.fetch = function(...args) {
            const [resource, config] = args;
            const method = (config?.method || 'GET').toUpperCase();

            // Add CSRF token to non-GET requests
            if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
                config = config || {};
                config.headers = config.headers || {};
                config.headers['X-CSRF-TOKEN'] = window.csrfToken;
            }

            return originalFetch.apply(this, [resource, config]);
        };

        // Intercept jQuery AJAX if available
        if (window.$ && $.ajaxSetup) {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': window.csrfToken
                },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-CSRF-TOKEN', window.csrfToken);
                }
            });
        }

        // Intercept axios if available
        if (window.axios) {
            window.axios.defaults.headers.common['X-CSRF-TOKEN'] = window.csrfToken;
        }

        console.log('✅ AJAX interceptor configured');
    }

    /**
     * Handle 419 errors gracefully
     */
    handle419Error(error) {
        console.error('❌ 419 Token Mismatch Error');
        
        // Refresh token
        this.refreshToken();
        
        // Show user-friendly message
        if (window.showErrorMessage) {
            window.showErrorMessage('Session expired. Please try again.');
        }
        
        return false;
    }
}

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.csrfManager = new CsrfTokenManager();
    });
} else {
    window.csrfManager = new CsrfTokenManager();
}
