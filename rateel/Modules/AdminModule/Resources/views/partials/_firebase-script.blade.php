<audio id="myAudio" preload="auto">
    <source src="{{asset('public/assets/admin-module/sound/safety-alert.mp3')}}" type="audio/mpeg">
</audio>
<script>
    "use strict"
    let audio = document.getElementById("myAudio");
    let isPlaying = false;
    let audioEnabled = false;

    // Set audio volume to maximum
    audio.volume = 1.0;

    // Enable audio on first user interaction (required by browsers)
    const enableAudio = function() {
        if (!audioEnabled) {
            audio.play().then(() => {
                audio.pause();
                audio.currentTime = 0;
                audioEnabled = true;
                console.log('Audio enabled for safety alerts');
            }).catch(e => console.log('Audio enable failed:', e));

            // Remove listeners after first interaction
            document.removeEventListener('click', enableAudio);
            document.removeEventListener('keydown', enableAudio);
        }
    };

    // Listen for any user interaction to enable audio
    document.addEventListener('click', enableAudio);
    document.addEventListener('keydown', enableAudio);

    // Add an event listener to replay the audio when it ends
    audio.addEventListener("ended", function () {
        if (isPlaying) {
            audio.currentTime = 0;
            audio.play().catch(function (error) {
                console.error("Error replaying audio:", error);
            });
        }
    });

    function playAudio() {
        isPlaying = true;

        // Reset to start if already playing
        audio.currentTime = 0;

        // Try to play with retry logic
        const attemptPlay = () => {
            audio.play()
                .then(() => {
                    console.log('Safety alert sound playing');
                    // Show browser notification as backup
                    showBrowserNotification('Safety Alert', 'New safety alert received!');
                })
                .catch(function (error) {
                    console.error("Error playing audio:", error);

                    // If autoplay blocked, show notification to user
                    if (error.name === 'NotAllowedError') {
                        console.warn('Audio autoplay blocked. User interaction required.');
                        showBrowserNotification('Safety Alert', 'New safety alert received! Click to enable sound.');
                    }

                    // Retry once after a short delay
                    if (!audioEnabled) {
                        setTimeout(() => {
                            audio.play().catch(e => console.error('Retry failed:', e));
                        }, 100);
                    }
                });
        };

        attemptPlay();
    }

    function stopAudio() {
        isPlaying = false;
        audio.pause();
        audio.currentTime = 0; // Reset to the start
    }

    // Show browser notification as backup alert
    function showBrowserNotification(title, body) {
        if ('Notification' in window && Notification.permission === 'granted') {
            try {
                new Notification(title, {
                    body: body,
                    icon: '{{asset("public/assets/admin-module/img/logo.png")}}',
                    requireInteraction: true,
                    tag: 'safety-alert'
                });
            } catch (e) {
                console.log('Browser notification failed:', e);
            }
        }
    }


    // Initialize Firebase
    firebase.initializeApp({
        apiKey: "{{ businessConfig(key: 'api_key',settingsType: NOTIFICATION_SETTINGS)?->value ?? '' }}",
        authDomain: "{{ businessConfig(key: 'auth_domain',settingsType: NOTIFICATION_SETTINGS)?->value ?? '' }}",
        projectId: "{{ businessConfig(key: 'project_id',settingsType: NOTIFICATION_SETTINGS)?->value ?? '' }}",
        storageBucket: "{{ businessConfig(key: 'storage_bucket',settingsType: NOTIFICATION_SETTINGS)?->value ?? '' }}",
        messagingSenderId: "{{ businessConfig(key: 'messaging_sender_id',settingsType: NOTIFICATION_SETTINGS)?->value ?? '' }}",
        appId: "{{ businessConfig(key: 'app_id',settingsType: NOTIFICATION_SETTINGS)?->value ?? '' }}",
        measurementId: "{{ businessConfig(key: 'measurement_id',settingsType: NOTIFICATION_SETTINGS)?->value ?? '' }}",
    });


    const messaging = firebase.messaging();

    function startFCM() {
        messaging
            .requestPermission()
            .then(function () {
                return messaging.getToken();
            })
            .then(function (token) {
                // console.log('FCM Token:', token);
                // Send the token to your backend to subscribe to topics
                subscribeTokenToBackend(token, 'admin_safety_alert_notification');
                subscribeTokenToBackend(token, 'admin_panic_alert_notification');
            }).catch(function (error) {
            console.error('Error getting permission or token:', error);
        });
    }

    function subscribeTokenToBackend(token, topic) {
        fetch('{{route('admin.subscribe-topic')}}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({token: token, topic: topic})
        }).then(response => {
            if (response.status < 200 || response.status >= 400) {
                return response.text().then(text => {
                    throw new Error(`Error subscribing to topic: ${response.status} - ${text}`);
                });
            }
        }).catch(error => {
            console.error('Subscription error:', error);
        });
    }

    messaging.onMessage(function (payload) {
        if (payload.data) {
            // Check if this is a panic alert or safety alert
            if (payload.data.type === 'panic_alert' || payload.data.alert_type === 'panic') {
                panicAlertNotification(payload.data);
                playAudio();
            } else {
                safetyAlertNotification(payload.data);
                playAudio();
                let safetyAlertIconMap = document.getElementsByClassName('safety-alert-icon-map');
                let zoneMessageDiv = document.getElementsByClassName('get-zone-message');
                getSafetyAlerts();
                if (zoneMessageDiv) {
                    getZoneMessage();
                }
                if (safetyAlertIconMap) {
                    fetchSafetyAlertIcon()
                }
                $('.zone-message').removeClass('invisible');
                sessionStorage.removeItem('showZoneMessage');
            }
        }
    })
    startFCM();

    function fetchSafetyAlertIcon() {
        let url = "{{ route('admin.fleet-map-safety-alert-icon-in-map') }}";
        $.ajax({
            url: url,
            method: 'GET',
            success: function (response) {
                $('.safety-alert-icon-map').empty().html(response);
                if ($('#safetyAlertMapIcon').length) {
                    $('#safetyAlertMapIcon').addClass('d-none');
                }
                if ($('#newSafetyAlertMapIcon').length) {
                    $('#newSafetyAlertMapIcon').removeClass('d-none');
                }
                $('.show-safety-alert-user-details').on('click', function () {
                    localStorage.setItem('safetyAlertUserDetailsStatus', true);
                });
            }
        })
    }

    function getZoneMessage() {
        let url = "{{ route('admin.fleet-map-zone-message') }}";
        $.ajax({
            url: url,
            method: 'GET',
            success: function (response) {
                $('.get-zone-message').empty().html(response);
                $('.zone-message-hide').on('click', function () {
                    $('.zone-message').addClass('invisible');
                    sessionStorage.setItem('showZoneMessage', 'false');
                });
            }
        })

    }

</script>
