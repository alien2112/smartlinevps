// ============================================
// 🧪 CHATBOT TEST SCRIPT
// ============================================
// Tests both customer and captain flows

const http = require('http');

const BASE_URL = process.env.TEST_URL || 'http://localhost:3000';
const TEST_USER_CUSTOMER = 'test-customer-001';
const TEST_USER_CAPTAIN = 'test-captain-001';

// Test cases
const tests = [
    // Customer tests
    {
        name: 'Customer - Greeting (Arabic)',
        user_id: TEST_USER_CUSTOMER,
        message: 'مرحبا',
        expected: { hasMessage: true, hasAction: true }
    },
    {
        name: 'Customer - Greeting (English)',
        user_id: TEST_USER_CUSTOMER,
        message: 'Hello',
        expected: { hasMessage: true, hasAction: true }
    },
    {
        name: 'Customer - Book Trip Intent',
        user_id: TEST_USER_CUSTOMER,
        message: 'عايز احجز رحلة',
        expected: { hasMessage: true, action: 'request_pickup_location' }
    },
    {
        name: 'Customer - Book Trip Intent (English)',
        user_id: TEST_USER_CUSTOMER,
        message: 'I need a ride',
        expected: { hasMessage: true, action: 'request_pickup_location' }
    },
    
    // Captain tests
    {
        name: 'Captain - Registration Status Check',
        user_id: TEST_USER_CAPTAIN,
        message: 'حالة التسجيل',
        expected: { hasMessage: true, userType: 'captain' }
    },
    {
        name: 'Captain - Registration Status Check (English)',
        user_id: TEST_USER_CAPTAIN,
        message: 'registration status',
        expected: { hasMessage: true, userType: 'captain' }
    }
];

/**
 * Send HTTP POST request
 */
function sendRequest(userId, message) {
    return new Promise((resolve, reject) => {
        const postData = JSON.stringify({
            user_id: userId,
            message: message
        });

        const options = {
            hostname: new URL(BASE_URL).hostname,
            port: new URL(BASE_URL).port || 3000,
            path: '/chat',
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Content-Length': Buffer.byteLength(postData)
            }
        };

        const req = http.request(options, (res) => {
            let data = '';

            res.on('data', (chunk) => {
                data += chunk;
            });

            res.on('end', () => {
                try {
                    const json = JSON.parse(data);
                    resolve({ status: res.statusCode, data: json });
                } catch (e) {
                    resolve({ status: res.statusCode, data: data, error: e.message });
                }
            });
        });

        req.on('error', (e) => {
            reject(e);
        });

        req.write(postData);
        req.end();
    });
}

/**
 * Run a single test
 */
async function runTest(test) {
    console.log(`\n🧪 Testing: ${test.name}`);
    console.log(`   User: ${test.user_id}`);
    console.log(`   Message: ${test.message}`);

    try {
        const result = await sendRequest(test.user_id, test.message);
        
        if (result.status !== 200) {
            console.log(`   ❌ FAILED: Status ${result.status}`);
            console.log(`   Response:`, result.data);
            return false;
        }

        const response = result.data;
        
        // Check expected fields
        let passed = true;
        const errors = [];

        if (test.expected.hasMessage && !response.message) {
            passed = false;
            errors.push('Missing message');
        }

        if (test.expected.hasAction && !response.action) {
            passed = false;
            errors.push('Missing action');
        }

        if (test.expected.action && response.action !== test.expected.action) {
            passed = false;
            errors.push(`Expected action ${test.expected.action}, got ${response.action}`);
        }

        if (test.expected.userType && response.userType !== test.expected.userType) {
            passed = false;
            errors.push(`Expected userType ${test.expected.userType}, got ${response.userType}`);
        }

        if (passed) {
            console.log(`   ✅ PASSED`);
            console.log(`   Response: ${response.message.substring(0, 100)}...`);
            console.log(`   Action: ${response.action}`);
            console.log(`   UserType: ${response.userType || 'customer'}`);
        } else {
            console.log(`   ❌ FAILED: ${errors.join(', ')}`);
            console.log(`   Response:`, JSON.stringify(response, null, 2));
        }

        return passed;

    } catch (error) {
        console.log(`   ❌ ERROR: ${error.message}`);
        return false;
    }
}

/**
 * Run all tests
 */
async function runAllTests() {
    console.log('='.repeat(60));
    console.log('🚗 SMARTLINE CHATBOT TEST SUITE');
    console.log('='.repeat(60));
    console.log(`Base URL: ${BASE_URL}`);
    console.log(`Total tests: ${tests.length}`);

    const results = [];
    for (const test of tests) {
        const passed = await runTest(test);
        results.push({ test: test.name, passed });
        
        // Wait a bit between tests
        await new Promise(resolve => setTimeout(resolve, 500));
    }

    // Summary
    console.log('\n' + '='.repeat(60));
    console.log('📊 TEST SUMMARY');
    console.log('='.repeat(60));
    
    const passed = results.filter(r => r.passed).length;
    const failed = results.filter(r => !r.passed).length;
    
    console.log(`✅ Passed: ${passed}`);
    console.log(`❌ Failed: ${failed}`);
    console.log(`📈 Success Rate: ${Math.round((passed / results.length) * 100)}%`);

    if (failed > 0) {
        console.log('\n❌ Failed Tests:');
        results.filter(r => !r.passed).forEach(r => {
            console.log(`   - ${r.test}`);
        });
    }

    process.exit(failed > 0 ? 1 : 0);
}

// Run tests
runAllTests().catch(error => {
    console.error('Fatal error:', error);
    process.exit(1);
});

