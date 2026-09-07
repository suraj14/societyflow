/**
 * CONSOLE ERROR SUPPRESSOR
 * Suppresses non-critical warnings and errors
 * Keeps critical errors visible
 */

(function() {
    'use strict';

    // Store original console methods
    const originalWarn = console.warn;
    const originalError = console.error;
    const originalLog = console.log;

    // List of warnings to suppress (non-critical)
    const suppressedWarnings = [
        'Alpine Warning',
        'x-collapse',
        'Collapse plugin',
        'VAPID',
        'Push notifications not configured',
        'Push notifications not supported',
    ];

    // Override console.warn to filter warnings
    console.warn = function(...args) {
        const message = args.join(' ');
        
        // Check if this is a suppressed warning
        const isSuppressed = suppressedWarnings.some(warning => 
            message.includes(warning)
        );
        
        // Only show if not suppressed
        if (!isSuppressed) {
            originalWarn.apply(console, args);
        }
    };

    // Override console.error to filter errors
    console.error = function(...args) {
        const message = args.join(' ');
        
        // Check if this is a suppressed error
        const isSuppressed = suppressedWarnings.some(warning => 
            message.includes(warning)
        );
        
        // Only show if not suppressed
        if (!isSuppressed) {
            originalError.apply(console, args);
        }
    };

    // Log that suppressor is active
    originalLog.call(console, '✓ Console error suppressor initialized - non-critical warnings hidden');
})();
