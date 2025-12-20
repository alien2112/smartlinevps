<?php

namespace Modules\Gateways\Traits;

use Twilio\Rest\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SMS Gateway Trait for General Messages (non-OTP)
 * 
 * Simplified to support only:
 * 1. SMS Smart (Beon.chat) - Primary for Egypt (cheapest local traffic)
 * 2. Twilio - Secondary/Testing (best free trial, global reach)
 * 
 * @package Modules\Gateways\Traits
 */
trait SmsGatewayForMessage
{
    /**
     * Send a general SMS message via the first available gateway
     * 
     * Priority:
     * 1. SMS Smart (Beon.chat) - Best for Egypt
     * 2. Twilio - Fallback / Testing
     *
     * @param string $receiver Phone number with country code
     * @param string $message Message content
     * @return string 'success', 'error', or 'not_found'
     */
    public static function send($receiver, $message): string
    {
        // 🥇 Primary: SMS Smart (Beon.chat) - Cheapest for Egypt
        $config = self::get_settings('sms_smart');
        if (isset($config) && $config['status'] == 1) {
            Log::info('SMS Gateway (Message): Using SMS Smart', ['receiver' => $receiver]);
            return self::sms_smart_message($receiver, $message, $config);
        }

        // 🥈 Secondary: Twilio - Best for testing/PoC
        $config = self::get_settings('twilio');
        if (isset($config) && $config['status'] == 1) {
            Log::info('SMS Gateway (Message): Using Twilio', ['receiver' => $receiver]);
            return self::twilio($receiver, $message, $config);
        }

        Log::warning('SMS Gateway (Message): No active SMS gateway found');
        return 'not_found';
    }

    /**
     * 🥇 SMS Smart (Beon.chat) - General Message Sending
     * 
     * ✅ Local Egyptian gateway
     * ✅ Much cheaper than international providers
     * ✅ Best delivery inside Egypt
     * 
     * @param string $receiver Phone number
     * @param string $message Message content
     * @param array $config Gateway configuration
     * @return string 'success' or 'error'
     */
    public static function sms_smart_message($receiver, $message, $config): string
    {
        $response = 'error';
        
        try {
            $token = env('BEON_API_TOKEN');
            
            if (empty($token)) {
                Log::error('SMS Smart (Message): BEON_API_TOKEN not configured');
                return 'error';
            }

            // For general messages, use a different Beon endpoint if available
            // Otherwise, we'll use the OTP endpoint with a custom message
            $apiResponse = Http::withHeaders([
                'beon-token' => $token,
            ])->asForm()->post('https://beon.chat/api/send/message', [
                'phoneNumber' => $receiver,
                'name'        => 'SmartLine',
                'type'        => 'sms',
                'message'     => $message,
                'reference'   => uniqid('msg_'),
            ]);

            if ($apiResponse->successful()) {
                Log::info('SMS Smart (Message): Message sent successfully', [
                    'receiver' => $receiver
                ]);
                $response = 'success';
            } else {
                Log::error('SMS Smart (Message): Failed to send', [
                    'receiver' => $receiver,
                    'status' => $apiResponse->status(),
                    'body' => $apiResponse->body()
                ]);
            }

        } catch (\Exception $e) {
            Log::error('SMS Smart (Message): Exception occurred', [
                'receiver' => $receiver,
                'error' => $e->getMessage()
            ]);
        }
        
        return $response;
    }

    /**
     * 🥈 Twilio - General Message Sending
     * 
     * ✅ Reliable global delivery
     * ✅ Excellent SDK
     * 
     * @param string $receiver Phone number
     * @param string $message Message content
     * @param array $config Gateway configuration
     * @return string 'success' or 'error'
     */
    public static function twilio($receiver, $message, $config): string
    {
        $response = 'error';
        
        if (!isset($config) || $config['status'] != 1) {
            return $response;
        }
        
        try {
            $sid = $config['sid'];
            $token = $config['token'];
            $messagingServiceSid = $config['messaging_service_sid'];

            if (empty($sid) || empty($token) || empty($messagingServiceSid)) {
                Log::error('Twilio (Message): Missing configuration');
                return 'error';
            }

            $twilio = new Client($sid, $token);
            $twilio->messages->create(
                $receiver,
                [
                    "messagingServiceSid" => $messagingServiceSid,
                    "body" => $message
                ]
            );
            
            Log::info('Twilio (Message): Message sent successfully', ['receiver' => $receiver]);
            $response = 'success';
            
        } catch (\Exception $exception) {
            Log::error('Twilio (Message): Failed to send', [
                'receiver' => $receiver,
                'error' => $exception->getMessage()
            ]);
        }
        
        return $response;
    }

    /**
     * Get SMS gateway settings from database
     *
     * @param string $name Gateway name (sms_smart, twilio)
     * @return array|null Configuration array or null
     */
    public static function get_settings($name)
    {
        $data = configSettings($name, 'sms_config');
        if (isset($data) && !is_null($data->live_values)) {
            return json_decode($data->live_values, true);
        }
        return null;
    }
}
