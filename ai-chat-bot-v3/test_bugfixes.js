/**
 * Test Script for Bug Fixes
 * Tests the two critical fixes:
 * 1. Confirm/Cancel pattern bug
 * 2. Language detection and consistency
 * 
 * Run: node test_bugfixes.js
 */

const axios = require('axios');

const BASE_URL = process.env.TEST_URL || 'http://localhost:3000';
const colors = {
    reset: '\x1b[0m',
    green: '\x1b[32m',
    red: '\x1b[31m',
    yellow: '\x1b[33m',
    blue: '\x1b[34m',
    cyan: '\x1b[36m'
};

let testsPassed = 0;
let testsFailed = 0;

function log(color, message) {
    console.log(`${color}${message}${colors.reset}`);
}

function assert(condition, testName, expected, actual) {
    if (condition) {
        testsPassed++;
        log(colors.green, `✓ PASS: ${testName}`);
        return true;
    } else {
        testsFailed++;
        log(colors.red, `✗ FAIL: ${testName}`);
        log(colors.yellow, `  Expected: ${expected}`);
        log(colors.yellow, `  Actual: ${actual}`);
        return false;
    }
}

async function sendMessage(userId, message) {
    try {
        const response = await axios.post(`${BASE_URL}/chat`, {
            user_id: userId,
            message: message
        });
        return response.data;
    } catch (error) {
        log(colors.red, `Error sending message: ${error.message}`);
        return null;
    }
}

async function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

// ============================================
// TEST 1: Confirm/Cancel Bug Fix
// ============================================
async function testConfirmCancelBug() {
    log(colors.cyan, '\n========================================');
    log(colors.cyan, 'TEST 1: Confirm/Cancel Bug Fix');
    log(colors.cyan, '========================================\n');

    const userId = `test_confirm_${Date.now()}`;

    // Step 1: Start booking
    log(colors.blue, '1. Starting booking flow...');
    let response = await sendMessage(userId, 'book a ride');
    await delay(500);

    // Step 2: Provide pickup
    log(colors.blue, '2. Providing pickup location...');
    response = await sendMessage(userId, 'Nasr City');
    await delay(500);

    // Step 3: Select first option
    log(colors.blue, '3. Selecting pickup...');
    response = await sendMessage(userId, '1');
    await delay(500);

    // Step 4: Provide destination
    log(colors.blue, '4. Providing destination...');
    response = await sendMessage(userId, 'Maadi');
    await delay(500);

    // Step 5: Select destination
    log(colors.blue, '5. Selecting destination...');
    response = await sendMessage(userId, '1');
    await delay(500);

    // Step 6: Select vehicle type
    log(colors.blue, '6. Selecting vehicle...');
    response = await sendMessage(userId, '1');
    await delay(500);

    // Step 7: Confirm booking
    log(colors.blue, '7. Confirming booking...');
    response = await sendMessage(userId, 'yes');
    await delay(500);

    const bookingConfirmed = response && (
        response.message.includes('confirmed') || 
        response.message.includes('تأكيد') ||
        response.action === 'confirm_booking'
    );
    assert(bookingConfirmed, 'Booking should be confirmed', 'Booking confirmed', response?.message);

    // Step 8: Request cancellation
    log(colors.blue, '8. Requesting trip cancellation...');
    response = await sendMessage(userId, 'cancel trip');
    await delay(500);

    const cancelPrompt = response && (
        response.message.includes('sure') || 
        response.message.includes('متأكد')
    );
    assert(cancelPrompt, 'Should ask for cancel confirmation', 'Cancel confirmation prompt', response?.message);

    // Step 9: THE CRITICAL TEST - Say "confirm trip"
    log(colors.blue, '9. CRITICAL: Saying "confirm trip" (should NOT cancel)...');
    response = await sendMessage(userId, 'confirm trip');
    await delay(500);

    // The bug: "confirm" was in the pattern, so it would cancel
    // The fix: "confirm" removed, so it should ask for clarification
    const didNotCancel = response && !(
        response.message.includes('cancelled') || 
        response.message.includes('إلغاء') ||
        response.action === 'cancel_trip'
    );
    assert(
        didNotCancel, 
        'BUG FIX: "confirm trip" should NOT cancel the trip',
        'Trip NOT cancelled (asks for clarification)',
        response?.message
    );

    // Step 10: Actually cancel with "yes"
    log(colors.blue, '10. Cancelling with "yes"...');
    response = await sendMessage(userId, 'yes');
    await delay(500);

    const tripCancelled = response && (
        response.message.includes('cancelled') || 
        response.message.includes('إلغاء') ||
        response.action === 'cancel_trip'
    );
    assert(tripCancelled, 'Trip should be cancelled with "yes"', 'Trip cancelled', response?.message);
}

// ============================================
// TEST 2: Language Switching - English to Arabic
// ============================================
async function testLanguageSwitchEnToAr() {
    log(colors.cyan, '\n========================================');
    log(colors.cyan, 'TEST 2: Language Switch (English → Arabic)');
    log(colors.cyan, '========================================\n');

    const userId = `test_lang_en_ar_${Date.now()}`;

    // Step 1: Start in English
    log(colors.blue, '1. Starting conversation in English...');
    let response = await sendMessage(userId, 'hello');
    await delay(500);

    const englishResponse = response && /[a-zA-Z]/.test(response.message);
    assert(englishResponse, 'Should respond in English', 'English response', response?.message);

    // Step 2: Switch to Arabic
    log(colors.blue, '2. Switching to Arabic...');
    response = await sendMessage(userId, 'عايز أحجز رحلة');
    await delay(500);

    const arabicResponse = response && /[\u0600-\u06FF]/.test(response.message);
    assert(arabicResponse, 'Should respond in Arabic', 'Arabic response', response?.message);

    // Step 3: Continue in Arabic (should stay in Arabic)
    log(colors.blue, '3. Continuing in Arabic (should stay Arabic)...');
    response = await sendMessage(userId, 'مساعدة');
    await delay(500);

    const stillArabic = response && /[\u0600-\u06FF]/.test(response.message);
    assert(stillArabic, 'Should still respond in Arabic (sticky session)', 'Arabic response', response?.message);
}

// ============================================
// TEST 3: Language Switch - Arabic to English
// ============================================
async function testLanguageSwitchArToEn() {
    log(colors.cyan, '\n========================================');
    log(colors.cyan, 'TEST 3: Language Switch (Arabic → English)');
    log(colors.cyan, '========================================\n');

    const userId = `test_lang_ar_en_${Date.now()}`;

    // Step 1: Start in Arabic
    log(colors.blue, '1. Starting conversation in Arabic...');
    let response = await sendMessage(userId, 'مرحبا');
    await delay(500);

    const arabicResponse = response && /[\u0600-\u06FF]/.test(response.message);
    assert(arabicResponse, 'Should respond in Arabic', 'Arabic response', response?.message);

    // Step 2: Switch to English
    log(colors.blue, '2. Switching to English...');
    response = await sendMessage(userId, 'I want to book a ride');
    await delay(500);

    const englishResponse = response && /[a-zA-Z]/.test(response.message);
    assert(englishResponse, 'Should respond in English', 'English response', response?.message);

    // Step 3: Continue in English
    log(colors.blue, '3. Continuing in English (should stay English)...');
    response = await sendMessage(userId, 'help');
    await delay(500);

    const stillEnglish = response && /[a-zA-Z]/.test(response.message);
    assert(stillEnglish, 'Should still respond in English (sticky session)', 'English response', response?.message);
}

// ============================================
// TEST 4: Explicit Language Command
// ============================================
async function testExplicitLanguageCommand() {
    log(colors.cyan, '\n========================================');
    log(colors.cyan, 'TEST 4: Explicit Language Command');
    log(colors.cyan, '========================================\n');

    const userId = `test_lang_explicit_${Date.now()}`;

    // Step 1: Start in English
    log(colors.blue, '1. Starting in English...');
    let response = await sendMessage(userId, 'hello');
    await delay(500);

    // Step 2: Explicit command to switch to Arabic
    log(colors.blue, '2. Explicit command: "reply in Arabic"...');
    response = await sendMessage(userId, 'reply in Arabic');
    await delay(500);

    const switchedToArabic = response && /[\u0600-\u06FF]/.test(response.message);
    assert(switchedToArabic, 'Should switch to Arabic on explicit command', 'Arabic response', response?.message);

    // Step 3: Explicit command to switch back to English
    log(colors.blue, '3. Explicit command: "كلمني إنجليزي"...');
    response = await sendMessage(userId, 'كلمني إنجليزي');
    await delay(500);

    const switchedToEnglish = response && /[a-zA-Z]/.test(response.message);
    assert(switchedToEnglish, 'Should switch to English on explicit command', 'English response', response?.message);
}

// ============================================
// TEST 5: Arabizi Handling
// ============================================
async function testArabiziHandling() {
    log(colors.cyan, '\n========================================');
    log(colors.cyan, 'TEST 5: Arabizi Handling');
    log(colors.cyan, '========================================\n');

    const userId = `test_arabizi_${Date.now()}`;

    // Step 1: Send Arabizi message
    log(colors.blue, '1. Sending Arabizi message...');
    let response = await sendMessage(userId, '3ayez ride');
    await delay(500);

    // Should ask for language preference
    const asksForPreference = response && (
        response.message.includes('Arabic') || 
        response.message.includes('English') ||
        response.message.includes('عربي') ||
        response.message.includes('إنجليزي')
    );
    assert(asksForPreference, 'Should ask for language preference on Arabizi', 'Language preference prompt', response?.message);

    // Step 2: Choose English
    log(colors.blue, '2. Choosing English...');
    response = await sendMessage(userId, 'English');
    await delay(500);

    const respondsInEnglish = response && /[a-zA-Z]/.test(response.message);
    assert(respondsInEnglish, 'Should respond in English after choice', 'English response', response?.message);
}

// ============================================
// TEST 6: Booking Flow in Arabic
// ============================================
async function testBookingFlowArabic() {
    log(colors.cyan, '\n========================================');
    log(colors.cyan, 'TEST 6: Complete Booking Flow in Arabic');
    log(colors.cyan, '========================================\n');

    const userId = `test_booking_ar_${Date.now()}`;

    // Step 1: Start in Arabic
    log(colors.blue, '1. Starting booking in Arabic...');
    let response = await sendMessage(userId, 'عايز أحجز رحلة');
    await delay(500);

    const arabicPickupPrompt = response && /[\u0600-\u06FF]/.test(response.message);
    assert(arabicPickupPrompt, 'Pickup prompt should be in Arabic', 'Arabic prompt', response?.message);

    // Step 2: Provide pickup in Arabic
    log(colors.blue, '2. Providing pickup in Arabic...');
    response = await sendMessage(userId, 'مدينة نصر');
    await delay(500);

    const arabicPickupOptions = response && /[\u0600-\u06FF]/.test(response.message);
    assert(arabicPickupOptions, 'Pickup options should be in Arabic', 'Arabic options', response?.message);

    // Continue flow...
    log(colors.blue, '3. Completing flow...');
    await sendMessage(userId, '1');
    await delay(500);
    await sendMessage(userId, 'المعادي');
    await delay(500);
    await sendMessage(userId, '1');
    await delay(500);
    response = await sendMessage(userId, '1');
    await delay(500);

    const arabicConfirmPrompt = response && /[\u0600-\u06FF]/.test(response.message);
    assert(arabicConfirmPrompt, 'Entire flow should remain in Arabic', 'Arabic throughout', response?.message);
}

// ============================================
// MAIN TEST RUNNER
// ============================================
async function runAllTests() {
    log(colors.cyan, '\n╔════════════════════════════════════════╗');
    log(colors.cyan, '║   SmartLine AI Chatbot - Bug Fixes    ║');
    log(colors.cyan, '║          Test Suite v1.0               ║');
    log(colors.cyan, '╚════════════════════════════════════════╝\n');

    log(colors.yellow, `Testing against: ${BASE_URL}\n`);

    try {
        await testConfirmCancelBug();
        await testLanguageSwitchEnToAr();
        await testLanguageSwitchArToEn();
        await testExplicitLanguageCommand();
        await testArabiziHandling();
        await testBookingFlowArabic();
    } catch (error) {
        log(colors.red, `\nTest suite error: ${error.message}`);
    }

    // Summary
    log(colors.cyan, '\n========================================');
    log(colors.cyan, 'TEST SUMMARY');
    log(colors.cyan, '========================================\n');

    const total = testsPassed + testsFailed;
    const passRate = total > 0 ? ((testsPassed / total) * 100).toFixed(1) : 0;

    log(colors.green, `✓ Passed: ${testsPassed}`);
    log(colors.red, `✗ Failed: ${testsFailed}`);
    log(colors.yellow, `  Total:  ${total}`);
    log(colors.cyan, `  Pass Rate: ${passRate}%\n`);

    if (testsFailed === 0) {
        log(colors.green, '🎉 All tests passed! Both bugs are fixed.\n');
        process.exit(0);
    } else {
        log(colors.red, '⚠️  Some tests failed. Please review the output above.\n');
        process.exit(1);
    }
}

// Run tests
runAllTests().catch(error => {
    log(colors.red, `Fatal error: ${error.message}`);
    process.exit(1);
});

