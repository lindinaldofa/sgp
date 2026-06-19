<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contact;
use App\Models\Tenant;

class TemplateVariableResolverService
{
    public function resolve(string $template, Contact $contact, Tenant $tenant): string
    {
        $nameParts = explode(' ', $contact->name, 2);

        $variables = [
            '{{contact.name}}' => $contact->name,
            '{{contact.first_name}}' => $nameParts[0],
            '{{contact.city}}' => $contact->city ?? '',
            '{{contact.zone}}' => $contact->electoral_zone ?? '',
            '{{tenant.name}}' => $tenant->name,
            '{{tenant.phone}}' => $tenant->settings['phone'] ?? '',
        ];

        return str_replace(array_keys($variables), array_values($variables), $template);
    }
}
