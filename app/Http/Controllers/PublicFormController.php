<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ProcessFormSubmissionAction;
use App\Http\Resources\FormResource;
use App\Models\Form;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicFormController extends Controller
{
    public function show(string $slug): JsonResponse
    {
        $form = Form::where('slug', $slug)
            ->where('is_active', true)
            ->with('fields')
            ->firstOrFail();

        return (new FormResource($form))->response();
    }

    public function submit(
        Request $request,
        string $slug,
        ProcessFormSubmissionAction $action,
    ): JsonResponse {
        $form = Form::where('slug', $slug)
            ->where('is_active', true)
            ->with('fields')
            ->firstOrFail();

        // Validate required fields declared in the form definition
        $rules = [];
        foreach ($form->fields as $field) {
            if ($field->type->isLayout()) {
                continue;
            }
            $fieldRules = ['nullable', 'string', 'max:1000'];
            if ($field->is_required) {
                $fieldRules[0] = 'required';
            }
            $rules[$field->name] = $fieldRules;
        }

        $validated = $request->validate($rules);

        $submission = $action->execute($form, $validated, $request);

        return response()->json([
            'message'     => $form->success_message ?: 'Formulário enviado com sucesso!',
            'redirectUrl' => $form->redirect_url,
            'submission'  => ['ulid' => $submission->ulid],
        ]);
    }
}
