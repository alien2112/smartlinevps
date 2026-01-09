// ============================================
// 🧪 TEST SUITE FOR BOT ENGINE
// ============================================

const Bot = require('./bot_engine');

function runTest(name, userId, input, context, expectedState, expectedHandoff) {
    console.log(`\n🔹 TEST: ${name}`);
    console.log(`Input: "${input}"`);
    console.log(`Context:`, context ? "Active Ride" : "No Ride");

    const result = Bot.processMessage(userId, input, context);

    console.log(`Result Text:\n"${result.text}"`);
    console.log(`New State: ${result.state}`);
    console.log(`Handoff: ${result.handoff}`);

    const stateMatch = result.state === expectedState;
    const handoffMatch = result.handoff === expectedHandoff;

    if (stateMatch && handoffMatch) {
        console.log(`✅ PASS`);
    } else {
        console.log(`❌ FAIL (Expected State: ${expectedState}, Handoff: ${expectedHandoff})`);
    }
}

// MOCK DATA
const activeRide = { exists: true, driverName: "Ahmed", pickup: "Mall", destination: "Airport" };
const noRide = { exists: false };

// --- 10 TEST CASES ---

// 1. Start with Active Ride
runTest("Start - Active Ride", "user1", "أريد عمل شكوى", activeRide, "RIDE_MENU", false);

// 2. Select 'Driver Late' (Option 1)
runTest("Menu - Select Late", "user1", "1", activeRide, "DRIVER_LATE_FLOW", false);

// 3. Late Flow - Report Still (Option 1)
runTest("Late - Still Waiting", "user1", "1", activeRide, "WAIT_OR_CANCEL", false);

// 4. Safety Override - Harassment (Confirmed)
runTest("Safety - Harassment (Confirmed)", "user2", "تحرش", activeRide, "SAFETY_ALERT", true);

// 4b. Safety Override - Speeding (Suspected)
// Expected: Safety Alert state BUT Handoff = FALSE (Ask first)
runTest("Safety - Speeding (Suspected)", "user_speed", "يمشي بسرعة", activeRide, "SAFETY_ALERT", false);

// 5. Safety Override - Unsafe Driving (Suspected -> Confirmed flow if answer is no)
// Test logic flow handled in engine by subsequent user reply
runTest("Safety - Driving (Suspected)", "user3", "سواقة متهورة", activeRide, "SAFETY_ALERT", false);

// 6. Safety Followup - "I am not safe"
runTest("Safety - Not Safe", "user3", "لا", activeRide, "ESCALATE", true);

// 7. General Menu - No Ride
runTest("Start - No Ride", "user4", "أبغى مساعدة", noRide, "GENERAL_MENU", false);

// 8. Explicit Handoff
runTest("Handoff Request", "user5", "أبغى موظف", activeRide, "ESCALATE", true);

// 9. Loop Prevention (Handoff again)
// Note: state is already ESCALATE from previous test if using same user or we simulate logic
// BotEngine stores state in memory, so user5 is now in ESCALATE
runTest("Handoff Loop", "user5", "يا ابن الحلال هات موظف", activeRide, "ESCALATE", true);

// 10. Fare Dispute
runTest("Fare Dispute", "user6", "السعر غالي", activeRide, "FARE_FLOW", false);
