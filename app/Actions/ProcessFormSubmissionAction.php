<?php

declare(strict_types=1);

namespace App\Actions;

use App\Jobs\SendNewContactAutomationJob;
use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ProcessFormSubmissionAction
{
    public function __construct(
        private readonly FindOrCreateContactAction $findOrCreateContact,
    ) {}

    /** @param array<string, mixed> $values */
    public function execute(Form $form, array $values, Request $request): FormSubmission
    {
        return DB::transaction(function () use ($form, $values, $request) {
            [$nativeData, $customData] = $this->extractContactData($form, $values);

            $contact = null;
            if ($nativeData !== [] || $customData !== []) {
                $contact = $this->findOrCreateContact->execute(
                    tenantId: (int) $form->tenant_id,
                    nativeData: $nativeData,
                    customData: $customData,
                    tagIds: $form->tag_id ? [$form->tag_id] : [],
                );
            }

            if ($contact && $form->tag_id) {
                SendNewContactAutomationJob::dispatch($contact->id, $form->tag_id, $contact->wasRecentlyCreated);
            }

            $submission = FormSubmission::create([
                'form_id' => $form->id,
                'tenant_id' => $form->tenant_id,
                'contact_id' => $contact?->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'submitted_at' => now(),
            ]);

            foreach ($form->fields as $field) {
                if ($field->type->isLayout()) {
                    continue;
                }
                $value = $values[$field->name] ?? null;
                $submission->values()->create([
                    'field_name' => $field->name,
                    'field_label' => $field->label,
                    'value' => is_bool($value) ? ($value ? '1' : '0') : $value,
                ]);
            }

            return $submission;
        });
    }

    /**
     * Splits the submission into native contact columns and custom field
     * values, based on each field's `maps_to`. A `maps_to` of `custom.{key}`
     * targets a tenant-defined custom field instead of a native column.
     *
     * @param array<string, mixed> $values
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function extractContactData(Form $form, array $values): array
    {
        $valuesByTarget = [];

        foreach ($form->fields as $field) {
            if (! $field->maps_to) {
                continue;
            }

            // The LGPD checkbox is authoritative whenever the form declares it: an
            // unchecked box (or one the visitor never touched) is still an explicit
            // "no", so it must not be confused with "field absent from the form",
            // which is what lets contacts fall back to the tenant-wide default.
            if ($field->maps_to === 'lgpd_consent') {
                $valuesByTarget['lgpd_consent'] = filter_var($values[$field->name] ?? false, FILTER_VALIDATE_BOOLEAN);

                continue;
            }

            if (array_key_exists($field->name, $values)) {
                $valuesByTarget[$field->maps_to] = $values[$field->name];
            }
        }

        return FindOrCreateContactAction::splitNativeAndCustom($valuesByTarget);
    }
}
