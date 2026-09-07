/**
 * SocietyFlow Push Notification Manager
 * Handles Web Push (VAPID) and FCM subscriptions
 */

class PushNotificationManager {
    constructor() {
        this.serviceWorkerPath = '/service-worker.js';
        this.subscriptionEndpoint = '/api/push/subscribe';
        this.unsubscriptionEndpoint = '/api/push/unsubscribe';
        this.vapidKeyEndpoint = '/api/push/vapid-key';
        this.isSupported = this.checkSupport();
    }

    /**
     * Check if push notifications are supported
     */
    checkSupport() {
        return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    }

    /**
     * Initialize push notifications
     */
    async init() {
        if (!this.isSupported) {
            console.warn('Push notifications not supported in this browser');
            return false;
        }

        try {
            // Register service worker
            const registration = await navigator.serviceWorker.register(this.serviceWorkerPath);
            console.log('Service Worker registered:', registration);

            // Request notification permission
            if (Notification.permission === 'default') {
                await this.requestPermission();
            }

            // Subscribe if permission granted
            if (Notification.permission === 'granted') {
                await this.subscribe();
            }

            return true;
        } catch (error) {
            console.error('Push notification initialization failed:', error);
            return false;
        }
    }

    /**
     * Request notification permission
     */
    async requestPermission() {
        try {
            const permission = await Notification.requestPermission();
            console.log('Notification permission:', permission);
            return permission === 'granted';
        } catch (error) {
            console.error('Permission request failed:', error);
            return false;
        }
    }

    /**
     * Subscribe to push notifications
     */
    async subscribe() {
        try {
            const registration = await navigator.serviceWorker.ready;
            
            // Get VAPID public key
            try {
                const vapidResponse = await fetch(this.vapidKeyEndpoint);
                
                // If endpoint doesn't exist (404), skip push notifications
                if (!vapidResponse.ok) {
                    console.log('Push notifications not configured (VAPID endpoint not available)');
                    return false;
                }
                
                const vapidData = await vapidResponse.json();

                if (!vapidData.success) {
                    console.log('VAPID key not available - push notifications disabled');
                    return false;
                }

                const vapidPublicKey = vapidData.vapid_public_key;

                // Subscribe to push manager
                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: this.urlBase64ToUint8Array(vapidPublicKey),
                });

                // Send subscription to server
                const response = await fetch(this.subscriptionEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.getCsrfToken(),
                    },
                    body: JSON.stringify({
                        endpoint: subscription.endpoint,
                        auth_key: this.arrayBufferToBase64(subscription.getKey('auth')),
                        p256dh_key: this.arrayBufferToBase64(subscription.getKey('p256dh')),
                        device_type: 'web',
                        browser: this.getBrowserName(),
                    }),
                });

                const data = await response.json();
                if (data.success) {
                    console.log('✓ Push subscription saved:', data.subscription_id);
                    return true;
                } else {
                    console.log('Push subscription not saved:', data.message);
                    return false;
                }
            } catch (vapidError) {
                console.log('Push notifications not available - skipping subscription');
                return false;
            }
        } catch (error) {
            console.error('Subscription failed:', error);
            return false;
        }
    }

    /**
     * Unsubscribe from push notifications
     */
    async unsubscribe() {
        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();

            if (!subscription) {
                console.log('No active subscription');
                return true;
            }

            // Notify server
            await fetch(this.unsubscriptionEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.getCsrfToken(),
                },
                body: JSON.stringify({
                    endpoint: subscription.endpoint,
                }),
            });

            // Unsubscribe from push manager
            await subscription.unsubscribe();
            console.log('Unsubscribed from push notifications');
            return true;
        } catch (error) {
            console.error('Unsubscription failed:', error);
            return false;
        }
    }

    /**
     * Check if user is subscribed
     */
    async isSubscribed() {
        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            return subscription !== null;
        } catch (error) {
            console.error('Error checking subscription:', error);
            return false;
        }
    }

    /**
     * Convert URL Base64 to Uint8Array
     */
    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }

        return outputArray;
    }

    /**
     * Convert ArrayBuffer to Base64
     */
    arrayBufferToBase64(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return window.btoa(binary);
    }

    /**
     * Get CSRF token
     */
    getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    /**
     * Get browser name
     */
    getBrowserName() {
        const ua = navigator.userAgent;
        if (ua.indexOf('Firefox') > -1) return 'Firefox';
        if (ua.indexOf('Chrome') > -1) return 'Chrome';
        if (ua.indexOf('Safari') > -1) return 'Safari';
        if (ua.indexOf('Edge') > -1) return 'Edge';
        return 'Unknown';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    const pushManager = new PushNotificationManager();
    if (pushManager.isSupported) {
        pushManager.init().catch(error => console.error('Push notification init error:', error));
    }
});
