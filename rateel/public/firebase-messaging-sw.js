importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-app.js');
importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-messaging.js');

firebase.initializeApp({
    apiKey: "AIzaSyDZX8v_gdgA1NHorU6P9xBm5oEfQ2tqB7U",
    authDomain: "smartline-36054.firebaseapp.com",
    projectId: "smartline-36054",
    storageBucket: "smartline-36054.firebasestorage.ap",
    messagingSenderId: "473905435046",
    appId: "1:473905435046:web:36d9c9788386d9e65d7f67",
    measurementId: "G-DSZKNKWJ7K"
});

const messaging = firebase.messaging();
messaging.setBackgroundMessageHandler(function (payload) {
    return self.registration.showNotification(payload.data.title, {
        body: payload.data.body ? payload.data.body : '',
        icon: payload.data.icon ? payload.data.icon : ''
    });
});