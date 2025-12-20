<?php

namespace Modules\Gateways\Traits;

use Twilio\Rest\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SMS Gateway Trait for SmartLine
 * 
 * Simplified to support only:
 * 1. SMS Smart (Beon.chat) - Primary for Egypt (cheapest local traffic)
 * 2. Twilio - Secondary/Testing (best free trial, global reach)
 * 
 * @package Modules\Gateways\Traits
 */
trait SmsGateway
{
    /**
     * Send OTP via the first available SMS gateway
     * 
     * Priority:
     * 1. SMS Smart (Beon.chat) - Best for Egypt
     * 2. Twilio - Fallback / Testing
     *
     * @param string $receiver Phone number with country code
     * @param string $otp OTP code to send
     * @return string 'success' or 'error'
     */
    public static function send($receiver, $otp): string
    {
        // 🥇 Primary: SMS Smart (Beon.chat) - Cheapest for Egypt
        $config = self::get_settings('sms_smart');
        if (isset($config) && $config['status'] == 1) {
            Log::info('SMS Gateway: Using SMS Smart (Beon.chat)', ['receiver' => $receiver]);
            return self::sms_smart($receiver, $otp);
        }

        // 🥈 Secondary: Twilio - Best for testing/PoC
        $config = self::get_settings('twilio');
        if (isset($config) && $config['status'] == 1) {
            Log::info('SMS Gateway: Using Twilio', ['receiver' => $receiver]);
            return self::twilio($receiver, $otp);
        }

        Log::warning('SMS Gateway: No active SMS gateway found');
        return 'not_found';
    }

    /**
     * 🥇 SMS Smart (Beon.chat) - Primary Gateway for Egypt
     * 
     * ✅ Local Egyptian gateway
     * ✅ Much cheaper than Twilio/Vonage for Egypt
     * ✅ Best delivery inside Egypt
     * ✅ Ideal for OTP / login codes
     * 
     * Required ENV: BEON_API_TOKEN
     * 
     * @param string $phone Phone number
     * @param string $otp OTP code
     * @return string|array 'success' or error array
     */
    public static function sms_smart($phone, $otp)
    {
        try {
            $token = env('BEON_API_TOKEN');
            
            if (empty($token)) {
                Log::error('SMS Smart: BEON_API_TOKEN not configured');
                return ['error' => true, 'message' => 'BEON_API_TOKEN not set'];
            }

            $response = Http::withHeaders([
                'beon-token' => $token,
            ])->asForm()->post('https://beon.chat/api/send/message/otp', [
                'phoneNumber' => $phone,
                'name'        => 'SmartLine',
                'type'        => 'sms',
                'otp_length'  => '6',
                'lang'        => 'ar',
                'reference'   => uniqid('otp_'),
                'custom_code' => $otp,
            ]);

            if ($response->successful()) {
                Log::info('SMS Smart: OTP sent successfully', [
                    'phone' => $phone,
                    'response' => $response->json()
                ]);
                return 'success';
            } else {
                Log::error('SMS Smart: Failed to send OTP', [
                    'phone' => $phone,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return ['error' => true, 'message' => $response->body()];
            }

        } catch (\Exception $e) {
            Log::error('SMS Smart: Exception occurred', [
                'phone' => $phone,
                'error' => $e->getMessage()
            ]);
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * 🥈 Twilio - Secondary Gateway (Testing/Global)
     * 
     * ✅ Free trial credit (~$10)
     * ✅ Excellent docs & SDKs
     * ✅ Global, reliable
     * ❌ Expensive for Egypt after trial
     * 
     * Required Config:
     * - sid: Account SID
     * - token: Auth Token
     * - messaging_service_sid: Messaging Service SID
     * - otp_template: Template with #OTP# placeholder
     *
     * @param string $receiver Phone number
     * @param string $otp OTP code
     * @return string 'success' or 'error'
     */
    public static function twilio($receiver, $otp): string
    {
        $config = self::get_settings('twilio');
        $response = 'error';
        
        if (!isset($config) || $config['status'] != 1) {
            Log::warning('Twilio: Gateway not configured or disabled');
            return $response;
        }

        try {
            $message = str_replace("#OTP#", $otp, $config['otp_template'] ?? 'Your SmartLine OTP is #OTP#');
            $sid = $config['sid'];
            $token = $config['token'];
            $messagingServiceSid = $config['messaging_service_sid'];

            if (empty($sid) || empty($token) || empty($messagingServiceSid)) {
                Log::error('Twilio: Missing configuration (sid, token, or messaging_service_sid)');
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
            
            Log::info('Twilio: OTP sent successfully', ['receiver' => $receiver]);
            $response = 'success';
            
        } catch (\Exception $exception) {
            Log::error('Twilio: Failed to send OTP', [
                'receiver' => $receiver,
                'error' => $exception->getMessage()
            ]);
            $response = 'error';
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
