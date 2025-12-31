// ============================================
// 🎬 ACTION DEFINITIONS FOR FLUTTER APP
// ============================================
// This file defines all possible actions that the
// backend can send to the Flutter app to execute.

/**
 * ACTION TYPES:
 * These are the commands that Flutter should handle
 */
const ACTION_TYPES = {
    // No action needed, just display the message
    NONE: 'none',

    // ===== TRIP BOOKING FLOW =====
    // Open map picker for pickup location
    REQUEST_PICKUP_LOCATION: 'request_pickup_location',

    // Open map picker for destination
    REQUEST_DESTINATION: 'request_destination',

    // Show ride type selection (economy, premium, etc.)
    SHOW_RIDE_OPTIONS: 'show_ride_options',

    // Show fare estimate before confirming
    SHOW_FARE_ESTIMATE: 'show_fare_estimate',

    // Confirm and create the trip
    CONFIRM_BOOKING: 'confirm_booking',

    // ===== TRIP TRACKING =====
    // Navigate to trip tracking screen
    SHOW_TRIP_TRACKING: 'show_trip_tracking',

    // Show driver info card
    SHOW_DRIVER_INFO: 'show_driver_info',

    // ===== TRIP ACTIONS =====
    // Cancel current trip
    CANCEL_TRIP: 'cancel_trip',

    // Show cancel trip confirmation dialog
    CONFIRM_CANCEL_TRIP: 'confirm_cancel_trip',

    // Contact driver (call/message)
    CONTACT_DRIVER: 'contact_driver',

    // ===== HISTORY & SUPPORT =====
    // Show trip history list
    SHOW_TRIP_HISTORY: 'show_trip_history',

    // Show specific trip details
    SHOW_TRIP_DETAILS: 'show_trip_details',

    // Open rating modal for a trip
    RATE_TRIP: 'rate_trip',

    // ===== PAYMENT =====
    // Show payment methods
    SHOW_PAYMENT_METHODS: 'show_payment_methods',

    // Add new payment method
    ADD_PAYMENT_METHOD: 'add_payment_method',

    // Show fare breakdown
    SHOW_FARE_BREAKDOWN: 'show_fare_breakdown',

    // ===== SAFETY =====
    // Trigger SOS/emergency
    TRIGGER_EMERGENCY: 'trigger_emergency',

    // Show safety screen
    SHOW_SAFETY_CENTER: 'show_safety_center',

    // Share live location
    SHARE_LIVE_LOCATION: 'share_live_location',

    // ===== ACCOUNT =====
    // Open profile settings
    SHOW_PROFILE: 'show_profile',

    // Show wallet/balance
    SHOW_WALLET: 'show_wallet',

    // ===== HUMAN HANDOFF =====
    // Connect to human support
    CONNECT_SUPPORT: 'connect_support',

    // Open in-app call to support
    CALL_SUPPORT: 'call_support',

    // ===== QUICK REPLIES =====
    // Show quick reply buttons
    SHOW_QUICK_REPLIES: 'show_quick_replies'
};

/**
 * UI HINT TYPES:
 * Additional UI hints for Flutter
 */
const UI_HINTS = {
    // Show typing indicator briefly before showing message
    TYPING_DELAY: 'typing_delay',

    // Show success animation
    SUCCESS_ANIMATION: 'success_animation',

    // Show error/warning state
    ERROR_STATE: 'error_state',

    // Highlight urgency (for safety issues)
    URGENT: 'urgent'
};

/**
 * Build a standard action response
 * @param {string} action - The action type from ACTION_TYPES
 * @param {object} data - Additional data for the action
 * @param {string[]} quickReplies - Quick reply options for the user
 * @param {string} uiHint - UI hint from UI_HINTS
 */
function buildAction(action, data = {}, quickReplies = [], uiHint = null) {
    return {
        action,
        data,
        quick_replies: quickReplies,
        ui_hint: uiHint
    };
}

/**
 * Pre-built action builders for common flows
 */
const ActionBuilders = {
    // ===== TRIP BOOKING =====
    requestPickup: () => buildAction(
        ACTION_TYPES.REQUEST_PICKUP_LOCATION,
        { map_type: 'pickup' },
        [],
        UI_HINTS.TYPING_DELAY
    ),

    requestDestination: (pickupData) => buildAction(
        ACTION_TYPES.REQUEST_DESTINATION,
        {
            map_type: 'destination',
            pickup: pickupData
        },
        [],
        UI_HINTS.TYPING_DELAY
    ),

    showRideOptions: (pickup, destination) => buildAction(
        ACTION_TYPES.SHOW_RIDE_OPTIONS,
        { pickup, destination },
        ['توفير', 'سمارت برو', 'في اي بي'],
        null
    ),

    showFareEstimate: (pickup, destination, rideType, estimatedFare) => buildAction(
        ACTION_TYPES.SHOW_FARE_ESTIMATE,
        {
            pickup,
            destination,
            ride_type: rideType,
            estimated_fare: estimatedFare,
            currency: 'EGP'
        },
        ['تأكيد الحجز', 'تغيير نوع الرحلة', 'إلغاء'],
        null
    ),

    confirmBooking: (tripData) => buildAction(
        ACTION_TYPES.CONFIRM_BOOKING,
        tripData,
        [],
        UI_HINTS.SUCCESS_ANIMATION
    ),

    // ===== TRIP TRACKING =====
    showTripTracking: (tripId) => buildAction(
        ACTION_TYPES.SHOW_TRIP_TRACKING,
        { trip_id: tripId },
        ['أين الكابتن؟', 'إلغاء الرحلة', 'اتصل بالكابتن'],
        null
    ),

    showDriverInfo: (tripId, driverData) => buildAction(
        ACTION_TYPES.SHOW_DRIVER_INFO,
        { trip_id: tripId, driver: driverData },
        ['اتصل بالكابتن', 'رسالة', 'إلغاء'],
        null
    ),

    // ===== TRIP ACTIONS =====
    confirmCancelTrip: (tripId, cancellationFee = 0) => buildAction(
        ACTION_TYPES.CONFIRM_CANCEL_TRIP,
        {
            trip_id: tripId,
            cancellation_fee: cancellationFee,
            currency: 'EGP'
        },
        ['نعم، إلغاء', 'لا، استمر'],
        null
    ),

    contactDriver: (tripId, driverPhone) => buildAction(
        ACTION_TYPES.CONTACT_DRIVER,
        {
            trip_id: tripId,
            phone: driverPhone,
            options: ['call', 'message']
        },
        ['اتصال', 'رسالة'],
        null
    ),

    // ===== HISTORY =====
    showTripHistory: () => buildAction(
        ACTION_TYPES.SHOW_TRIP_HISTORY,
        {},
        ['الرحلات المكتملة', 'الرحلات الملغاة', 'الكل'],
        null
    ),

    showTripDetails: (tripId) => buildAction(
        ACTION_TYPES.SHOW_TRIP_DETAILS,
        { trip_id: tripId },
        ['إيصال', 'دعم', 'تقييم'],
        null
    ),

    rateTrip: (tripId) => buildAction(
        ACTION_TYPES.RATE_TRIP,
        { trip_id: tripId },
        [],
        null
    ),

    // ===== PAYMENT =====
    showPaymentMethods: () => buildAction(
        ACTION_TYPES.SHOW_PAYMENT_METHODS,
        {},
        ['إضافة بطاقة', 'Apple Pay', 'نقدي'],
        null
    ),

    showFareBreakdown: (tripId, breakdown) => buildAction(
        ACTION_TYPES.SHOW_FARE_BREAKDOWN,
        { trip_id: tripId, breakdown },
        ['اعتراض على السعر', 'موافق'],
        null
    ),

    // ===== SAFETY =====
    triggerEmergency: (tripId = null) => buildAction(
        ACTION_TYPES.TRIGGER_EMERGENCY,
        { trip_id: tripId },
        [],
        UI_HINTS.URGENT
    ),

    shareLiveLocation: (tripId = null) => buildAction(
        ACTION_TYPES.SHARE_LIVE_LOCATION,
        { trip_id: tripId },
        [],
        UI_HINTS.URGENT
    ),

    // ===== HUMAN HANDOFF =====
    connectSupport: (reason, tripId = null) => buildAction(
        ACTION_TYPES.CONNECT_SUPPORT,
        { reason, trip_id: tripId },
        [],
        UI_HINTS.TYPING_DELAY
    ),

    // ===== SIMPLE RESPONSES =====
    noAction: (quickReplies = []) => buildAction(
        ACTION_TYPES.NONE,
        {},
        quickReplies,
        null
    ),

    showQuickReplies: (options) => buildAction(
        ACTION_TYPES.SHOW_QUICK_REPLIES,
        {},
        options,
        null
    )
};

module.exports = {
    ACTION_TYPES,
    UI_HINTS,
    buildAction,
    ActionBuilders
};
