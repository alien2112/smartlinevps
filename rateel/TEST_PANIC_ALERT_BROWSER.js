/**
 * PANIC ALERT SYSTEM - BROWSER DIAGNOSTIC TEST
 * 
 * Instructions:
 * 1. Open Admin Dashboard in browser
 * 2. Open Browser Console (F12 > Console tab)
 * 3. Copy and paste this entire script
 * 4. Press Enter
 * 
 * This will check all components and show you what's working.
 */

console.log('%c========================================', 'color: cyan; font-weight: bold');
console.log('%c  PANIC ALERT SYSTEM - DIAGNOSTIC TEST', 'color: cyan; font-weight: bold');
console.log('%c========================================', 'color: cyan; font-weight: bold');
console.log('');

// Test 1: Check if Firebase is initialized
console.log('%c1. Firebase Initialization', 'color: yellow; font-weight: bold');
if (typeof firebase !== 'undefined') {
    console.log('✅ Firebase SDK loaded');
    console.log('   Version:', firebase.SDK_VERSION || 'Unknown');
} else {
    console.log('❌ Firebase SDK NOT loaded');
}
console.log('');

// Test 2: Check if Firebase Messaging is available
console.log('%c2. Firebase Messaging', 'color: yellow; font-weight: bold');
if (typeof firebase !== 'undefined' && firebase.messaging) {
    console.log('✅ Firebase Messaging available');
    try {
        const messaging = firebase.messaging();
        console.log('✅ Messaging instance created');
    } catch (e) {
        console.log('❌ Error creating messaging instance:', e.message);
    }
} else {
    console.log('❌ Firebase Messaging NOT available');
}
console.log('');

// Test 3: Check notification permissions
console.log('%c3. Browser Notification Permissions', 'color: yellow; font-weight: bold');
if ('Notification' in window) {
    console.log('✅ Notification API supported');
    console.log('   Permission status:', Notification.permission);
    if (Notification.permission === 'granted') {
        console.log('   ✅ Notifications ALLOWED');
    } else if (Notification.permission === 'denied') {
        console.log('   ❌ Notifications DENIED - Please enable in browser settings');
    } else {
        console.log('   ⚠️  Notifications NOT YET REQUESTED - Will be requested on page load');
    }
} else {
    console.log('❌ Notification API NOT supported');
}
console.log('');

// Test 4: Check if Service Worker is registered
console.log('%c4. Service Worker', 'color: yellow; font-weight: bold');
if ('serviceWorker' in navigator) {
    console.log('✅ Service Worker API supported');
    navigator.serviceWorker.getRegistrations().then(registrations => {
        if (registrations.length > 0) {
            console.log('✅ Service Worker(s) registered:', registrations.length);
            registrations.forEach((reg, index) => {
                console.log(`   ${index + 1}. Scope:`, reg.scope);
                console.log(`      Active:`, reg.active ? '✅' : '❌');
            });
        } else {
            console.log('⚠️  No Service Workers registered yet');
            console.log('   This is OK if page just loaded. Refresh page and check again.');
        }
    });
} else {
    console.log('❌ Service Worker API NOT supported');
}
console.log('');

// Test 5: Check if audio element exists
console.log('%c5. Audio System', 'color: yellow; font-weight: bold');
const audioElement = document.getElementById('myAudio');
if (audioElement) {
    console.log('✅ Audio element found');
    console.log('   Source:', audioElement.querySelector('source')?.src || 'Not set');
    console.log('   Can play:', audioElement.canPlayType('audio/mpeg') ? '✅' : '❌');
    
    // Test if audio can play
    const playPromise = audioElement.play();
    if (playPromise !== undefined) {
        playPromise.then(() => {
            console.log('✅ Audio CAN autoplay (playing now)');
            audioElement.pause();
            audioElement.currentTime = 0;
            console.log('   (Audio stopped after test)');
        }).catch((error) => {
            console.log('⚠️  Autoplay blocked by browser policy');
            console.log('   This is normal. Audio will play after user interaction.');
            console.log('   Error:', error.name);
        });
    }
} else {
    console.log('❌ Audio element NOT found (ID: myAudio)');
}
console.log('');

// Test 6: Check if functions exist
console.log('%c6. JavaScript Functions', 'color: yellow; font-weight: bold');
const functions = [
    'playAudio',
    'stopAudio',
    'panicAlertNotification',
    'safetyAlertNotification',
    'startFCM',
    'subscribeTokenToBackend'
];

functions.forEach(funcName => {
    if (typeof window[funcName] === 'function') {
        console.log(`✅ ${funcName}()`);
    } else {
        console.log(`❌ ${funcName}() NOT FOUND`);
    }
});
console.log('');

// Test 7: Check if modal exists
console.log('%c7. Panic Alert Modal', 'color: yellow; font-weight: bold');
const modal = document.getElementById('panicAlertNotificationModal');
if (modal) {
    console.log('✅ Panic Alert Modal exists');
    const elements = [
        { id: 'panicAlertNotificationTitle', name: 'Title' },
        { id: 'panicAlertNotificationSubtitle', name: 'Subtitle' },
        { id: 'panicAlertCustomerName', name: 'Customer Name' },
        { id: 'panicAlertCustomerPhone', name: 'Customer Phone' },
        { id: 'panicAlertReason', name: 'Reason' },
        { id: 'panicAlertMapLink', name: 'Map Link' },
        { id: 'panicCheckLater', name: 'Check Later Button' },
        { id: 'panicBtnClose', name: 'Close Button' }
    ];
    
    let allElementsFound = true;
    elements.forEach(el => {
        const elem = document.getElementById(el.id);
        if (elem) {
            console.log(`   ✅ ${el.name}`);
        } else {
            console.log(`   ❌ ${el.name} NOT FOUND`);
            allElementsFound = false;
        }
    });
    
    if (allElementsFound) {
        console.log('   ✅ All modal elements present');
    }
} else {
    console.log('❌ Panic Alert Modal NOT found');
}
console.log('');

// Test 8: Check Service Worker file accessibility
console.log('%c8. Service Worker File', 'color: yellow; font-weight: bold');
fetch('/firebase-messaging-sw.js', { method: 'HEAD' })
    .then(response => {
        if (response.ok) {
            console.log('✅ Service Worker file accessible');
            console.log('   URL: /firebase-messaging-sw.js');
            console.log('   Status:', response.status);
            console.log('   Content-Type:', response.headers.get('content-type'));
        } else {
            console.log('❌ Service Worker file NOT accessible');
            console.log('   Status:', response.status);
        }
    })
    .catch(error => {
        console.log('❌ Error checking Service Worker file:', error.message);
    });
console.log('');

// Test 9: Check Sound file accessibility
console.log('%c9. Sound File', 'color: yellow; font-weight: bold');
fetch('/assets/admin-module/sound/safety-alert.mp3', { method: 'HEAD' })
    .then(response => {
        if (response.ok) {
            console.log('✅ Sound file accessible');
            console.log('   URL: /assets/admin-module/sound/safety-alert.mp3');
            console.log('   Status:', response.status);
            console.log('   Content-Type:', response.headers.get('content-type'));
            console.log('   Size:', response.headers.get('content-length'), 'bytes');
        } else {
            console.log('❌ Sound file NOT accessible');
            console.log('   Status:', response.status);
        }
    })
    .catch(error => {
        console.log('❌ Error checking Sound file:', error.message);
    });
console.log('');

// Summary
setTimeout(() => {
    console.log('');
    console.log('%c========================================', 'color: green; font-weight: bold');
    console.log('%c  DIAGNOSTIC TEST COMPLETE', 'color: green; font-weight: bold');
    console.log('%c========================================', 'color: green; font-weight: bold');
    console.log('');
    console.log('%cNext Steps:', 'color: cyan; font-weight: bold');
    console.log('1. Review all ✅ and ❌ above');
    console.log('2. If all tests pass, trigger a panic alert from Flutter');
    console.log('3. You should hear sound + see modal');
    console.log('');
    console.log('%cTo manually test sound:', 'color: yellow');
    console.log('   playAudio()  // Start sound');
    console.log('   stopAudio()  // Stop sound');
    console.log('');
    console.log('%cTo manually test modal:', 'color: yellow');
    console.log('   panicAlertNotification({');
    console.log('       title: "Test Alert",');
    console.log('       description: "This is a test",');
    console.log('       customer_name: "John Doe",');
    console.log('       customer_phone: "+1234567890",');
    console.log('       reason: "Testing",');
    console.log('       lat: 31.1020976,');
    console.log('       lng: 29.7684019');
    console.log('   })');
    console.log('');
}, 2000);
