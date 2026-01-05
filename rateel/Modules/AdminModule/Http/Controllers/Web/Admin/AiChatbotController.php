<?php

namespace Modules\AdminModule\Http\Controllers\Web\Admin;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Modules\BusinessManagement\Entities\BusinessSetting;
use Brian2694\Toastr\Facades\Toastr;

class AiChatbotController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('admin');
    }

    /**
     * Display the AI Chatbot configuration page.
     * @return Renderable
     */
    public function index()
    {
        $isEnabled = BusinessSetting::where(['key_name' => 'ai_chatbot_enable', 'settings_type' => 'ai_config'])->first();
        $prompt = BusinessSetting::where(['key_name' => 'ai_chatbot_prompt', 'settings_type' => 'ai_config'])->first();
        $rateLimitPerMinute = BusinessSetting::where(['key_name' => 'ai_chatbot_rate_limit', 'settings_type' => 'ai_config'])->first();
        $maxTokens = BusinessSetting::where(['key_name' => 'ai_chatbot_max_tokens', 'settings_type' => 'ai_config'])->first();

        // Get recent chat conversations (user + assistant messages grouped) - cached for 2 minutes
        $chatHistory = Cache::remember('ai_chatbot_chat_history', 120, function () {
            try {
                return DB::table('ai_chat_history')
                    ->select('user_id', 'content', 'role', 'action_type', 'created_at')
                    ->orderBy('created_at', 'desc')
                    ->limit(100)
                    ->get()
                    ->groupBy('user_id')
                    ->map(function ($messages) {
                        return $messages->sortBy('created_at')->values();
                    })
                    ->take(10);
            } catch (\Exception $e) {
                return collect([]);
            }
        });

        // Get chatbot stats (cached for 5 minutes)
        $stats = Cache::remember('ai_chatbot_stats', 300, function () {
            try {
                return [
                    'total_conversations' => DB::table('ai_chat_history')->distinct('user_id')->count('user_id'),
                    'total_messages' => DB::table('ai_chat_history')->count(),
                    'today_messages' => DB::table('ai_chat_history')->whereDate('created_at', today())->count(),
                ];
            } catch (\Exception $e) {
                return [
                    'total_conversations' => 0,
                    'total_messages' => 0,
                    'today_messages' => 0,
                ];
            }
        });

        // Prompt embedding templates
        $promptTemplates = $this->getPromptTemplates();

        return view('adminmodule::admin.chatbot.index', compact('isEnabled', 'prompt', 'rateLimitPerMinute', 'maxTokens', 'chatHistory', 'stats', 'promptTemplates'));
    }

    /**
     * Get prompt embedding templates
     */
    private function getPromptTemplates(): array
    {
        return [
            [
                'name' => 'Greeting Style',
                'snippets' => [
                    ['label' => 'Friendly Greeting', 'text' => 'Always greet users warmly and introduce yourself as the SmartLine AI assistant.'],
                    ['label' => 'Professional Greeting', 'text' => 'Maintain a professional tone. Begin each conversation by confirming how you can assist.'],
                    ['label' => 'Bilingual Greeting', 'text' => 'Greet users in their preferred language (Arabic or English). Respond in the same language the user uses.'],
                ]
            ],
            [
                'name' => 'Behavior Rules',
                'snippets' => [
                    ['label' => 'Stay On Topic', 'text' => 'Only answer questions related to ride booking, trip management, and SmartLine services. Politely decline unrelated requests.'],
                    ['label' => 'Error Handling', 'text' => 'If you cannot understand a request, ask clarifying questions. Never make assumptions about trip details.'],
                    ['label' => 'Safety First', 'text' => 'Prioritize user safety. Provide emergency contact information when users report safety concerns.'],
                    ['label' => 'No Personal Data', 'text' => 'Never ask for or store sensitive personal information like passwords, credit card numbers, or ID numbers.'],
                ]
            ],
            [
                'name' => 'Service Knowledge',
                'snippets' => [
                    ['label' => 'Trip Booking', 'text' => 'You can help users book trips by collecting pickup location, destination, and ride type preferences.'],
                    ['label' => 'Trip Status', 'text' => 'You can provide real-time trip status updates including driver location and estimated arrival time.'],
                    ['label' => 'Payment Info', 'text' => 'Explain that payment can be made via cash or digital wallet. Guide users on how to add payment methods.'],
                    ['label' => 'Complaint Handling', 'text' => 'Listen to complaints empathetically. Collect trip ID and issue details, then escalate to support if needed.'],
                ]
            ],
            [
                'name' => 'Response Style',
                'snippets' => [
                    ['label' => 'Concise Responses', 'text' => 'Keep responses brief and to the point. Avoid lengthy explanations unless specifically requested.'],
                    ['label' => 'Step-by-Step', 'text' => 'When explaining processes, use numbered steps for clarity.'],
                    ['label' => 'Emoji Friendly', 'text' => 'Use appropriate emojis to make responses friendly and engaging, but don\'t overuse them.'],
                    ['label' => 'Formal Arabic', 'text' => 'When responding in Arabic, use Modern Standard Arabic (فصحى) for clarity.'],
                ]
            ],
        ];
    }

    /**
     * Update the AI configuration.
     * @param Request $request
     * @return Renderable
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'ai_chatbot_prompt' => 'required|string|min:10|max:5000',
            'ai_chatbot_rate_limit' => 'nullable|integer|min:1|max:100',
            'ai_chatbot_max_tokens' => 'nullable|integer|min:50|max:1000',
        ]);

        try {
            DB::beginTransaction();

            BusinessSetting::updateOrCreate(
                ['key_name' => 'ai_chatbot_enable', 'settings_type' => 'ai_config'],
                ['value' => $request->has('ai_chatbot_enable') ? 1 : 0]
            );

            BusinessSetting::updateOrCreate(
                ['key_name' => 'ai_chatbot_prompt', 'settings_type' => 'ai_config'],
                ['value' => strip_tags($validated['ai_chatbot_prompt'])]
            );

            if (isset($validated['ai_chatbot_rate_limit'])) {
                BusinessSetting::updateOrCreate(
                    ['key_name' => 'ai_chatbot_rate_limit', 'settings_type' => 'ai_config'],
                    ['value' => $validated['ai_chatbot_rate_limit']]
                );
            }

            if (isset($validated['ai_chatbot_max_tokens'])) {
                BusinessSetting::updateOrCreate(
                    ['key_name' => 'ai_chatbot_max_tokens', 'settings_type' => 'ai_config'],
                    ['value' => $validated['ai_chatbot_max_tokens']]
                );
            }

            DB::commit();

            // Clear cached prompt in chatbot service
            Cache::forget('ai_chatbot_system_prompt');
            Cache::forget('ai_chatbot_stats');
            Cache::forget('ai_chatbot_chat_history');

            Log::info('AI Chatbot configuration updated', [
                'admin_id' => auth()->id(),
                'enabled' => $request->has('ai_chatbot_enable'),
            ]);

            Toastr::success(translate('AI_Configuration_Updated_Successfully'));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update AI Chatbot config', ['error' => $e->getMessage()]);
            Toastr::error(translate('Failed_to_update_configuration'));
        }

        return back();
    }

    /**
     * Display chat logs with pagination
     */
    public function logs(Request $request)
    {
        $query = DB::table('ai_chat_history')
            ->select('ai_chat_history.*')
            ->orderBy('created_at', 'desc');

        // Filter by user_id if provided
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by date range
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $logs = $query->paginate(50);

        return view('adminmodule::admin.chatbot.logs', compact('logs'));
    }

    /**
     * Clear chat history for a specific user
     */
    public function clearUserHistory(Request $request)
    {
        $request->validate([
            'user_id' => 'required|string|max:36',
        ]);

        try {
            $deleted = DB::table('ai_chat_history')
                ->where('user_id', $request->user_id)
                ->delete();

            DB::table('ai_conversation_state')
                ->where('user_id', $request->user_id)
                ->delete();

            Cache::forget('ai_chatbot_chat_history');
            Cache::forget('ai_chatbot_stats');

            Log::info('AI chat history cleared', [
                'admin_id' => auth()->id(),
                'target_user_id' => $request->user_id,
                'messages_deleted' => $deleted,
            ]);

            return response()->json(['success' => true, 'deleted' => $deleted]);
        } catch (\Exception $e) {
            Log::error('Failed to clear AI chat history', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Failed to clear history'], 500);
        }
    }

    /**
     * Get chatbot health status (for monitoring)
     */
    public function health()
    {
        try {
            $chatbotUrl = config('services.ai_chatbot.url', 'http://localhost:3001');
            $response = @file_get_contents($chatbotUrl . '/health', false, stream_context_create([
                'http' => ['timeout' => 5]
            ]));

            if ($response) {
                $data = json_decode($response, true);
                return response()->json([
                    'laravel' => 'ok',
                    'chatbot' => $data['status'] ?? 'unknown',
                    'database' => $data['database'] ?? 'unknown',
                ]);
            }

            return response()->json([
                'laravel' => 'ok',
                'chatbot' => 'unreachable',
            ], 503);
        } catch (\Exception $e) {
            return response()->json([
                'laravel' => 'ok',
                'chatbot' => 'error',
                'error' => $e->getMessage(),
            ], 503);
        }
    }
}
