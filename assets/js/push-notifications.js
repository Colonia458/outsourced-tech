// assets/js/push-notifications.js - Web Push Notifications Client

class PushNotifications {
    constructor() {
        this.swPath = '/outsourced/sw.js';
        this.apiEndpoint = '/outsourced/api/v1/push.php';
        this.publicKey = null;
        this.subscription = null;
    }

    async init() {
        try {
            // Check if push notifications are supported
            if (!('PushManager' in window)) {
                console.log('Push notifications not supported');
                return false;
            }

            // Get VAPID public key from server
            const response = await fetch(this.apiEndpoint);
            const data = await response.json();
            
            if (data.public_key) {
                this.publicKey = this.dataToUint8Array(data.public_key);
            }

            // Check existing subscription
            const registration = await navigator.serviceWorker.ready;
            this.subscription = await registration.pushManager.getSubscription();

            return true;
        } catch (error) {
            console.error('Failed to initialize push notifications:', error);
            return false;
        }
    }

    async subscribe() {
        try {
            const registration = await navigator.serviceWorker.ready;
            
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.publicKey
            });

            // Save to server
            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'subscribe',
                    endpoint: subscription.endpoint,
                    keys: {
                        p256dh: this.arrayBufferToBase64(subscription.getKey('p256dh')),
                        auth: this.arrayBufferToBase64(subscription.getKey('auth'))
                    },
                    browser: this.getBrowserName()
                })
            });

            const result = await response.json();
            
            if (result.success) {
                this.subscription = subscription;
                this.showNotification('Notifications Enabled', 'You will now receive updates about your orders!');
                return true;
            }

            return false;
        } catch (error) {
            console.error('Failed to subscribe to push notifications:', error);
            return false;
        }
    }

    async unsubscribe() {
        try {
            if (this.subscription) {
                await this.subscription.unsubscribe();
                
                // Notify server
                await fetch(this.apiEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'unsubscribe',
                        endpoint: this.subscription.endpoint
                    })
                });

                this.subscription = null;
                return true;
            }
            return false;
        } catch (error) {
            console.error('Failed to unsubscribe:', error);
            return false;
        }
    }

    async sendTestNotification() {
        try {
            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'test'
                })
            });

            const result = await response.json();
            return result;
        } catch (error) {
            console.error('Failed to send test notification:', error);
            return { success: false, error: error.message };
        }
    }

    dataToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }

        return outputArray;
    }

    arrayBufferToBase64(buffer) {
        let binary = '';
        const bytes = new Uint8Array(buffer);
        const len = bytes.byteLength;

        for (let i = 0; i < len; i++) {
            binary += String.fromCharCode(bytes[i]);
        }

        return window.btoa(binary);
    }

    getBrowserName() {
        const ua = navigator.userAgent;
        if (ua.indexOf('Firefox') > -1) return 'firefox';
        if (ua.indexOf('Chrome') > -1) return 'chrome';
        if (ua.indexOf('Safari') > -1) return 'safari';
        if (ua.indexOf('Edge') > -1) return 'edge';
        return 'unknown';
    }

    showNotification(title, body) {
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification(title, {
                body: body,
                icon: '/outsourced/assets/images/logo.png',
                badge: '/outsourced/assets/images/logo.png'
            });
        }
    }
}

// Global instance
const pushNotifications = new PushNotifications();

// Initialize on page load
document.addEventListener('DOMContentLoaded', async () => {
    await pushNotifications.init();
});
