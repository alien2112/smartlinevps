/**
 * WebSocket Testing Script for SmartLine Real-time Service
 *
 * Usage:
 * node test-websocket.js
 *
 * This script simulates a driver connecting to the WebSocket service,
 * going online, and listening for ride requests.
 */

const io = require('socket.io-client');

// Configuration
const NODEJS_URL = 'http://localhost:3000';
const JWT_TOKEN = 'YOUR_JWT_TOKEN_HERE'; // Replace with actual token from driver login

// Driver data
const DRIVER_DATA = {
  location: {
    latitude: 30.0444,
    longitude: 31.2357
  },
  vehicle_category_id: 'your-vehicle-category-id',
  vehicle_id: 'your-vehicle-id',
  name: 'Test Driver'
};

console.log('🚀 Starting WebSocket Test Client...\n');
console.log('📍 Node.js URL:', NODEJS_URL);
console.log('🔑 JWT Token:', JWT_TOKEN.substring(0, 20) + '...\n');

// Connect to WebSocket server
const socket = io(NODEJS_URL, {
  auth: {
    token: JWT_TOKEN
  },
  transports: ['websocket', 'polling']
});

// Connection events
socket.on('connect', () => {
  console.log('✅ Connected to WebSocket server');
  console.log('📱 Socket ID:', socket.id);
  console.log('\n--- Going Online ---\n');

  // Go online
  socket.emit('driver:online', DRIVER_DATA);
});

socket.on('connect_error', (error) => {
  console.error('❌ Connection Error:', error.message);
  if (error.message.includes('Authentication')) {
    console.log('\n💡 Tip: Make sure to replace JWT_TOKEN with a valid token from driver login');
  }
});

socket.on('disconnect', (reason) => {
  console.log('❌ Disconnected:', reason);
});

// Driver-specific events
socket.on('ride:new', (data) => {
  console.log('\n🔔 NEW RIDE REQUEST RECEIVED!');
  console.log('📦 Data:', JSON.stringify(data, null, 2));
  console.log('\n--- You can now accept this ride ---');
  console.log('Run: socket.emit("driver:accept:ride", { rideId: "' + data.rideId + '" })');
  console.log('');
});

socket.on('ride:accept:success', (data) => {
  console.log('\n✅ RIDE ACCEPTED SUCCESSFULLY!');
  console.log('📦 Data:', JSON.stringify(data, null, 2));
  console.log('');
});

socket.on('ride:accept:failed', (data) => {
  console.log('\n❌ RIDE ACCEPTANCE FAILED!');
  console.log('📦 Data:', JSON.stringify(data, null, 2));
  console.log('');
});

socket.on('ride:taken', (data) => {
  console.log('\n⚠️ RIDE ALREADY TAKEN BY ANOTHER DRIVER');
  console.log('📦 Data:', JSON.stringify(data, null, 2));
  console.log('');
});

socket.on('ride:started', (data) => {
  console.log('\n🚗 RIDE STARTED');
  console.log('📦 Data:', JSON.stringify(data, null, 2));
  console.log('');
});

socket.on('ride:completed', (data) => {
  console.log('\n✅ RIDE COMPLETED');
  console.log('📦 Data:', JSON.stringify(data, null, 2));
  console.log('');
});

socket.on('ride:cancelled', (data) => {
  console.log('\n❌ RIDE CANCELLED');
  console.log('📦 Data:', JSON.stringify(data, null, 2));
  console.log('Cancelled by:', data.cancelledBy);
  console.log('');
});

// Generic error handler
socket.on('error', (error) => {
  console.error('\n❌ Error:', error);
  console.log('');
});

// Simulate location updates every 3 seconds
let locationUpdateInterval;
socket.on('connect', () => {
  if (locationUpdateInterval) clearInterval(locationUpdateInterval);

  locationUpdateInterval = setInterval(() => {
    const location = {
      latitude: DRIVER_DATA.location.latitude + (Math.random() - 0.5) * 0.001,
      longitude: DRIVER_DATA.location.longitude + (Math.random() - 0.5) * 0.001,
      speed: Math.random() * 60,
      heading: Math.random() * 360,
      accuracy: 10
    };

    socket.emit('driver:location', location);
    console.log('📍 Location updated:', location.latitude.toFixed(6), location.longitude.toFixed(6));
  }, 3000);
});

socket.on('disconnect', () => {
  if (locationUpdateInterval) {
    clearInterval(locationUpdateInterval);
    locationUpdateInterval = null;
  }
});

// Interactive commands
console.log('\n--- Available Commands ---\n');
console.log('To accept a ride, emit:');
console.log('  socket.emit("driver:accept:ride", { rideId: "YOUR_RIDE_ID" })');
console.log('');
console.log('To go offline, emit:');
console.log('  socket.emit("driver:offline")');
console.log('');
console.log('To manually update location, emit:');
console.log('  socket.emit("driver:location", { latitude: 30.0444, longitude: 31.2357 })');
console.log('');
console.log('Press Ctrl+C to exit\n');

// Handle graceful shutdown
process.on('SIGINT', () => {
  console.log('\n\n🛑 Shutting down...');
  socket.emit('driver:offline');
  socket.disconnect();
  process.exit(0);
});

// Keep the script running
process.stdin.resume();
