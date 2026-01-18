<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\BusinessManagement\Entities\BusinessSetting;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $privacyPolicy = BusinessSetting::where('key_name', 'privacy_policy')
            ->where('settings_type', 'pages_settings')
            ->first();

        if ($privacyPolicy) {
            $value = $privacyPolicy->value;

            // English terms to be replaced
            $englishTerms = '
<hr>
<h2>Location Data Collection and Usage</h2>
<h3>1. Location Data Collection</h3>
<p>We collect location data to help users find nearby service providers and improve service accuracy.</p>

<h3>2. Location Data Privacy</h3>
<p>Location data is not shared with third parties and is used only within the app functionality.</p>';

            // Arabic terms
            $arabicTerms = '
<hr>
<h2>جمع واستخدام بيانات الموقع</h2>
<h3>1. جمع بيانات الموقع</h3>
<p>نقوم بجمع بيانات الموقع لمساعدة المستخدمين في العثور على مقدمي الخدمات القريبين وتحسين دقة الخدمة.</p>

<h3>2. خصوصية بيانات الموقع</h3>
<p>لا يتم مشاركة بيانات الموقع مع أطراف ثالثة ويتم استخدامها فقط ضمن وظائف التطبيق.</p>';

            // Replace English with Arabic
            $value['long_description'] = str_replace($englishTerms, $arabicTerms, $value['long_description'] ?? '');

            $privacyPolicy->value = $value;
            $privacyPolicy->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $privacyPolicy = BusinessSetting::where('key_name', 'privacy_policy')
            ->where('settings_type', 'pages_settings')
            ->first();

        if ($privacyPolicy) {
            $value = $privacyPolicy->value;

            // Arabic terms to be replaced
            $arabicTerms = '
<hr>
<h2>جمع واستخدام بيانات الموقع</h2>
<h3>1. جمع بيانات الموقع</h3>
<p>نقوم بجمع بيانات الموقع لمساعدة المستخدمين في العثور على مقدمي الخدمات القريبين وتحسين دقة الخدمة.</p>

<h3>2. خصوصية بيانات الموقع</h3>
<p>لا يتم مشاركة بيانات الموقع مع أطراف ثالثة ويتم استخدامها فقط ضمن وظائف التطبيق.</p>';

            // English terms
            $englishTerms = '
<hr>
<h2>Location Data Collection and Usage</h2>
<h3>1. Location Data Collection</h3>
<p>We collect location data to help users find nearby service providers and improve service accuracy.</p>

<h3>2. Location Data Privacy</h3>
<p>Location data is not shared with third parties and is used only within the app functionality.</p>';

            // Replace Arabic with English
            $value['long_description'] = str_replace($arabicTerms, $englishTerms, $value['long_description'] ?? '');

            $privacyPolicy->value = $value;
            $privacyPolicy->save();
        }
    }
};
