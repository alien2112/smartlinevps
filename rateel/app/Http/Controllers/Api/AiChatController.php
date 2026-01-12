<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AiChatController extends Controller
{
    private $chatbotUrl;

    public function __construct()
    {
        $this->chatbotUrl = config('services.nodejs_realtime.url', 'http://localhost:3000');
    }

    /**
     * Send message to AI chatbot - Customer
     * POST /api/customer/ai-chat
     */
    public function customerChat(Request $request): JsonResponse
    {
        return $this->sendChatMessage($request, 'customer');
    }

    /**
     * Send message to AI chatbot - Driver
     * POST /api/driver/ai-chat
     */
    public function driverChat(Request $request): JsonResponse
    {
        return $this->sendChatMessage($request, 'driver');
    }

    /**
     * Core chat message handler
     */
    private function sendChatMessage(Request $request, string $userType): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|min:1|max:2000',
        ]);

        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
                'error' => 'User not authenticated'
            ], 401);
        }

        try {
            // Prepare the payload for the Node.js chatbot
            $payload = [
                'user_id' => $user->id,
                'message' => $request->input('message'),
                'user_type' => $userType,
                'language' => $request->input('language', 'ar'),
            ];

            // Call the Node.js chatbot
            $response = Http::timeout(30)->post($this->chatbotUrl . '/chat', $payload);

            if (!$response->successful()) {
                Log::error('Chatbot API failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'user_id' => $user->id,
                    'user_type' => $userType,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to get chatbot response',
                    'error' => 'Chatbot service unavailable'
                ], 503);
            }

            $chatbotResponse = $response->json();

            // Store chat history in database
            try {
                DB::table('ai_chat_history')->insert([
                    'user_id' => $user->id,
                    'user_type' => $userType,
                    'content' => $request->input('message'),
                    'role' => 'user',
                    'action_type' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('ai_chat_history')->insert([
                    'user_id' => $user->id,
                    'user_type' => $userType,
                    'content' => $chatbotResponse['message'] ?? '',
                    'role' => 'assistant',
                    'action_type' => $chatbotResponse['action'] ?? 'none',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to store chat history', [
                    'error' => $e->getMessage(),
                    'user_id' => $user->id,
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $chatbotResponse,
                'message' => 'Message sent successfully',
            ], 200);

        } catch (\Exception $e) {
            Log::error('Chatbot error', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'user_type' => $userType,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get chat history
     * GET /api/customer/ai-chat/history
     */
    public function getChatHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        $userType = $request->input('user_type', 'customer');
        $limit = $request->input('limit', 50);

        try {
            $history = DB::table('ai_chat_history')
                ->where('user_id', $user->id)
                ->where('user_type', $userType)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->reverse()
                ->values();

            return response()->json([
                'success' => true,
                'data' => $history,
                'count' => $history->count(),
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to fetch chat history', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch chat history',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear chat history
     * DELETE /api/customer/ai-chat/history
     */
    public function clearChatHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        $userType = $request->input('user_type', 'customer');

        try {
            $deleted = DB::table('ai_chat_history')
                ->where('user_id', $user->id)
                ->where('user_type', $userType)
                ->delete();

            Log::info('Chat history cleared', [
                'user_id' => $user->id,
                'user_type' => $userType,
                'messages_deleted' => $deleted,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Chat history cleared',
                'deleted_count' => $deleted,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to clear chat history', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to clear chat history',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
