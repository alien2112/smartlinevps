/**
 * Test Laravel Reverb WebSocket Connection
 * 
 * Run: node test_reverb_websocket.js
 */

const WebSocket = require('ws');

const REVERB_URL = 'wss://smartline-it.com/app';
const REVERB_KEY = 'drivemond';

console.log('🔌 Testing Laravel Reverb WebSocket Connection...\n');
console.log(`URL: ${REVERB_URL}`);
console.log(`Key: ${REVERB_KEY}\n`);

// Create WebSocket connection
const ws = new WebSocket(REVERB_URL, {
  headers: {
    'Origin': 'https://smartline-it.com'
  }
});

ws.on('open', function open() {
  console.log('✅ WebSocket connection opened!');
  console.log('✅ Reverb is publicly accessible!\n');
  
  // Send Pusher protocol handshake
  const handshake = {
    event: 'pusher:subscribe',
    data: {
      channel: 'public-test'
    }
  };
  
  console.log('Sending handshake...');
  ws.send(JSON.stringify(handshake));
});

ws.on('message', function message(data) {
  console.log('📨 Received:', data.toString());
  
  try {
    const msg = JSON.parse(data.toString());
    if (msg.event === 'pusher:connection_established') {
      console.log('✅ Connection established successfully!');
      console.log('✅ Reverb is working correctly!\n');
    }
  } catch (e) {
    console.log('Raw message:', data.toString());
  }
});

ws.on('error', function error(err) {
  console.error('❌ WebSocket error:', err.message);
  console.error('Full error:', err);
});

ws.on('close', function close(code, reason) {
  console.log(`\n⚠️ Connection closed: ${code} - ${reason || 'No reason'}`);
});

// Timeout after 10 seconds
setTimeout(() => {
  if (ws.readyState === WebSocket.OPEN) {
    console.log('\n✅ Test completed - Reverb is accessible!');
    ws.close();
  } else {
    console.log('\n⏱️ Connection timeout');
    process.exit(1);
  }
}, 10000);
