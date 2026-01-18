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

            // Add location data terms as separate sections
            $locationTerms = '
<hr>
<h2>Location Data Collection and Usage</h2>
<h3>1. Location Data Collection</h3>
<p>We collect location data to help users find nearby service providers and improve service accuracy.</p>

<h3>2. Location Data Privacy</h3>
<p>Location data is not shared with third parties and is used only within the app functionality.</p>';

            // Append the new terms to the existing long description
            $value['long_description'] = ($value['long_description'] ?? '') . $locationTerms;

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

            // Remove the location data terms section
            $value['long_description'] = str_replace([
                '<hr>',
                '<h2>Location Data Collection and Usage</h2>',
                '<h3>1. Location Data Collection</h3>',
                '<p>We collect location data to help users find nearby service providers and improve service accuracy.</p>',
                '',
                '<h3>2. Location Data Privacy</h3>',
                '<p>Location data is not shared with third parties and is used only within the app functionality.</p>'
            ], '', $value['long_description'] ?? '');

            $privacyPolicy->value = $value;
            $privacyPolicy->save();
        }
    }
};
