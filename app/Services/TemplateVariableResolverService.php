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

        $resolved = str_replace(array_keys($variables), array_values($variables), $template);

        return $this->resolveCustomFields($resolved, $contact);
    }

    /**
     * Custom fields are dynamic per tenant, so they can't be a fixed lookup
     * table like the native ones above — `{{contact.custom.<key>}}` is
     * resolved against the contact's own custom_data instead, matching
     * whatever key was defined for that field in Settings.
     */
    private function resolveCustomFields(string $template, Contact $contact): string
    {
        $result = preg_replace_callback(
            '/\{\{contact\.custom\.([a-z0-9_]+)\}\}/i',
            function (array $matches) use ($contact) {
                $value = $contact->custom_data[$matches[1]] ?? '';

                if (is_bool($value)) {
                    return $value ? 'Sim' : 'Não';
                }

                return (string) $value;
            },
            $template,
        );

        return $result ?? $template;
    }
}
