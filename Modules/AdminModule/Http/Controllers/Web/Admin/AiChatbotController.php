<?php

namespace Modules\AdminModule\Http\Controllers\Web\Admin;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\BusinessManagement\Entities\BusinessSetting;
use Brian2694\Toastr\Facades\Toastr;

class AiChatbotController extends Controller
{
    /**
     * Display the AI Chatbot configuration page.
     * @return Renderable
     */
    public function index()
    {
        $isEnabled = BusinessSetting::where(['key_name' => 'ai_chatbot_enable', 'settings_type' => 'ai_config'])->first();
        $prompt = BusinessSetting::where(['key_name' => 'ai_chatbot_prompt', 'settings_type' => 'ai_config'])->first();
        
        // Mock data for logs - in production this would come from a ChatLog model
        $logs = []; 
        
        return view('adminmodule::admin.chatbot.index', compact('isEnabled', 'prompt', 'logs'));
    }

    /**
     * Update the AI configuration.
     * @param Request $request
     * @return Renderable
     */
    public function update(Request $request)
    {
        $request->validate([
            'ai_chatbot_prompt' => 'required|string',
        ]);

        BusinessSetting::updateOrCreate(
            ['key_name' => 'ai_chatbot_enable', 'settings_type' => 'ai_config'],
            ['value' => $request->has('ai_chatbot_enable') ? 1 : 0]
        );

        BusinessSetting::updateOrCreate(
            ['key_name' => 'ai_chatbot_prompt', 'settings_type' => 'ai_config'],
            ['value' => $request->ai_chatbot_prompt]
        );

        Toastr::success(translate('AI_Configuration_Updated_Successfully'));
        return back();
    }
    
    public function logs()
    {
        // Placeholder for logs view
        return view('adminmodule::admin.chatbot.logs');
    }
}
