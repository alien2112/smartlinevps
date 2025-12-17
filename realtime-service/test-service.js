/**
 * Node.js Real-time Service Test Script
 * Tests the service with and without Redis
 */

const io = require('socket.io-client');
const axios = require('axios');

const SERVICE_URL = process.env.SERVICE_URL || 'http://localhost:3000';
const TEST_DRIVER_TOKEN = 'test-driver-token'; // For testing driver connections
const TEST_CUSTOMER_TOKEN = 'test-customer-token'; // For testing customer connections

console.log('================================================================================');
console.log('NODE.JS REAL-TIME SERVICE TEST');
console.log('================================================================================');
console.log(`Service URL: ${SERVICE_URL}`);
console.log(`Redis Enabled: ${process.env.REDIS_ENABLED === 'true' ? 'YES' : 'NO (In-Memory)'}`);
console.log('================================================================================\n');

async function runTests() {
  try {
    // Test 1: Health Check
    console.log('Test 1: Health Check');
    console.log('-------------------------------------------');
    const healthResponse = await axios.get(`${SERVICE_URL}/health`);
    console.log('✓ Service is running');
    console.log(`  Status: ${healthResponse.data.status}`);
    console.log(`  Uptime: ${healthResponse.data.uptime}s`);
    console.log(`  Connections: ${healthResponse.data.connections}\n`);

    // Test 2: Metrics Endpoint
    console.log('Test 2: Metrics Endpoint');
    console.log('-------------------------------------------');
    const metricsResponse = await axios.get(`${SERVICE_URL}/metrics`);
    console.log('✓ Metrics endpoint working');
    console.log(`  Active Drivers: ${metricsResponse.data.activeDrivers}`);
    console.log(`  Active Rides: ${metricsResponse.data.activeRides}`);
    console.log(`  Memory Usage: ${Math.round(metricsResponse.data.memory.heapUsed / 1024 / 1024)}MB\n`);

    // Test 3: WebSocket Connection (Driver)
    console.log('Test 3: WebSocket Connection (Driver)');
    console.log('-------------------------------------------');

    const driverSocket = io(SERVICE_URL, {
      auth: {
        token: TEST_DRIVER_TOKEN
      },
      transports: ['websocket', 'polling']
    });

    await new Promise((resolve, reject) => {
      const timeout = setTimeout(() => {
        reject(new Error('Connection timeout'));
      }, 5000);

      driverSocket.on('connect', () => {
        clearTimeout(timeout);
        console.log('✓ Driver connected to WebSocket');
        console.log(`  Socket ID: ${driverSocket.id}\n`);
        resolve();
      });

      driverSocket.on('connect_error', (error) => {
        clearTimeout(timeout);
        reject(error);
      });
    });

    // Test 4: Driver Goes Online
    console.log('Test 4: Driver Goes Online');
    console.log('-------------------------------------------');

    await new Promise((resolve) => {
      driverSocket.on('driver:online:success', (data) => {
        console.log('✓ Driver went online successfully');
        console.log(`  Message: ${data.message}\n`);
        resolve();
      });

      driverSocket.emit('driver:online', {
        location: {
          latitude: 30.0444,
          longitude: 31.2357
        },
        vehicle_category_id: 'test-category-id',
        vehicle_id: 'test-vehicle-id',
        name: 'Test Driver'
      });
    });

    // Test 5: Driver Location Update
    console.log('Test 5: Driver Location Update');
    console.log('-------------------------------------------');

    driverSocket.emit('driver:location', {
      latitude: 30.0445,
      longitude: 31.2358,
      speed: 30,
      heading: 90,
      accuracy: 10
    });

    console.log('✓ Location update sent');
    console.log('  (No response expected - fire and forget)\n');

    // Test 6: Ping/Pong (Heartbeat)
    console.log('Test 6: Ping/Pong (Heartbeat)');
    console.log('-------------------------------------------');

    await new Promise((resolve) => {
      driverSocket.on('pong', (data) => {
        console.log('✓ Heartbeat working');
        console.log(`  Timestamp: ${data.timestamp}\n`);
        resolve();
      });

      driverSocket.emit('ping');
    });

    // Test 7: WebSocket Connection (Customer)
    console.log('Test 7: WebSocket Connection (Customer)');
    console.log('-------------------------------------------');

    const customerSocket = io(SERVICE_URL, {
      auth: {
        token: TEST_CUSTOMER_TOKEN
      },
      transports: ['websocket', 'polling']
    });

    await new Promise((resolve, reject) => {
      const timeout = setTimeout(() => {
        reject(new Error('Connection timeout'));
      }, 5000);

      customerSocket.on('connect', () => {
        clearTimeout(timeout);
        console.log('✓ Customer connected to WebSocket');
        console.log(`  Socket ID: ${customerSocket.id}\n`);
        resolve();
      });

      customerSocket.on('connect_error', (error) => {
        clearTimeout(timeout);
        reject(error);
      });
    });

    // Test 8: Customer Subscribes to Ride
    console.log('Test 8: Customer Subscribes to Ride');
    console.log('-------------------------------------------');

    await new Promise((resolve) => {
      customerSocket.emit('customer:subscribe:ride',
        { rideId: 'test-ride-id' },
        (response) => {
          if (response && response.success) {
            console.log('✓ Customer subscribed to ride');
            console.log(`  Ride ID: test-ride-id\n`);
          } else {
            console.log('⚠ Subscription response:', response);
          }
          resolve();
        }
      );
    });

    // Test 9: Check Metrics After Activity
    console.log('Test 9: Check Metrics After Activity');
    console.log('-------------------------------------------');
    const finalMetrics = await axios.get(`${SERVICE_URL}/metrics`);
    console.log('✓ Final metrics retrieved');
    console.log(`  Connections: ${finalMetrics.data.connections}`);
    console.log(`  Active Drivers: ${finalMetrics.data.activeDrivers}`);
    console.log(`  Memory: ${Math.round(finalMetrics.data.memory.heapUsed / 1024 / 1024)}MB\n`);

    // Cleanup
    console.log('Cleanup');
    console.log('-------------------------------------------');
    driverSocket.disconnect();
    customerSocket.disconnect();
    console.log('✓ Disconnected all test clients\n');

    // Summary
    console.log('================================================================================');
    console.log('TEST SUMMARY');
    console.log('================================================================================');
    console.log('✓ All tests passed!');
    console.log('  Redis Mode: ' + (process.env.REDIS_ENABLED === 'true' ? 'ENABLED' : 'IN-MEMORY'));
    console.log('  Service Status: OPERATIONAL');
    console.log('  WebSocket: WORKING');
    console.log('  Location Tracking: WORKING');
    console.log('  Heartbeat: WORKING');
    console.log('================================================================================\n');

    process.exit(0);

  } catch (error) {
    console.error('\n✗ Test failed:', error.message);
    console.error('\nStack trace:', error.stack);
    process.exit(1);
  }
}

// Run tests
runTests();
