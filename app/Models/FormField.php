<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FormFieldType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormField extends Model
{
    protected $fillable = [
        'form_id', 'type', 'label', 'name', 'placeholder',
        'help_text', 'default_value', 'is_required',
        'maps_to', 'options', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type'        => FormFieldType::class,
            'is_required' => 'boolean',
            'options'     => 'array',
        ];
    }

    /** @return BelongsTo<Form, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
