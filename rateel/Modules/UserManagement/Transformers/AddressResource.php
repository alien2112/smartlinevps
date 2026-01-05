<?php

namespace Modules\UserManagement\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            "id" => $this->id,
            "user_id" => $this->user_id,
            "latitude" => $this->latitude,
            "longitude" => $this->longitude,
            "city" => $this->city,
            "street" => $this->street,
            "house" => $this->house,
            "zip_code" => $this->zip_code,
            "country" => $this->country,
            "contact_person_name" => $this->contact_person_name,
            "contact_person_phone" => $this->contact_person_phone,
            "address" => $this->fixEncoding($this->address),
            "address_label" => $this->address_label,
            "created_at" => $this->created_at,
        ];
    }

    private function fixEncoding($string)
    {
        if (empty($string)) {
            return $string;
        }

        // Try to convert "UTF-8 displayed as CP437" back to raw bytes (CP437)
        // This effectively "undoes" the double-encoding if the system treated raw UTF-8 bytes as CP437 characters.
        // We use //IGNORE to skip any characters that don't map, but really we want to know if it maps cleanly.
        // However, a perfect mapping suggests it IS corrupted in this specific way.
        
        $fixed = @iconv('UTF-8', 'CP437', $string);

        if ($fixed !== false && mb_check_encoding($fixed, 'UTF-8')) {
            // If the result is valid UTF-8, it's highly likely we successfully recovered the original Arabic/UTF-8 string.
            // But we must be careful: ASCII text is also valid UTF-8 and valid CP437.
            // If the original string matches the fixed string (common for ASCII), just return it.
            // But if they differ, and the result is valid UTF-8, use the fixed one.
            
            // Heuristic: If the original string contains multi-byte characters (which likely look like garbage),
            // and the fixed string is shorter (bytes compressed back to chars), use the fixed one.
            
            if ($fixed !== $string) {
                 return $fixed;
            }
        }

        return $string;
    }

}
