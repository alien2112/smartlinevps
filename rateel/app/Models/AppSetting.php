<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UserManagement\Entities\User;

class AppSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'label',
        'description',
        'validation_rules',
        'default_value',
        'updated_by_admin_id',
    ];

    protected $casts = [
        'validation_rules' => 'array',
    ];

    /**
     * Get the admin who last updated this setting
     */
    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_admin_id');
    }

    /**
     * Get the typed value
     */
    public function getTypedValueAttribute(): mixed
    {
        return match ($this->type) {
            'integer' => (int) $this->value,
            'float' => (float) $this->value,
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json', 'array' => json_decode($this->value, true),
            default => $this->value,
        };
    }

    /**
     * Get the typed default value
     */
    public function getTypedDefaultValueAttribute(): mixed
    {
        return match ($this->type) {
            'integer' => (int) $this->default_value,
            'float' => (float) $this->default_value,
            'boolean' => filter_var($this->default_value, FILTER_VALIDATE_BOOLEAN),
            'json', 'array' => json_decode($this->default_value, true),
            default => $this->default_value,
        };
    }

    /**
     * Scope by group
     */
    public function scopeGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    /**
     * Get validation constraints
     */
    public function getConstraints(): array
    {
        return $this->validation_rules ?? [];
    }

    /**
     * Validate a value against constraints
     */
    public function validateValue($value): array
    {
        $errors = [];
        $constraints = $this->getConstraints();

        if (empty($constraints)) {
            return $errors;
        }

        $typedValue = match ($this->type) {
            'integer' => (int) $value,
            'float' => (float) $value,
            default => $value,
        };

        if (isset($constraints['min']) && $typedValue < $constraints['min']) {
            $errors[] = "Value must be at least {$constraints['min']}";
        }

        if (isset($constraints['max']) && $typedValue > $constraints['max']) {
            $errors[] = "Value must be at most {$constraints['max']}";
        }

        return $errors;
    }
}
