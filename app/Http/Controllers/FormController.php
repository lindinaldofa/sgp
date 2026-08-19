<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\FormResource;
use App\Http\Resources\FormSubmissionResource;
use App\Models\Form;
use App\Models\FormField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FormController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $forms = Form::where('tenant_id', $this->typedUser($request)->tenant_id)
            ->withCount('submissions')
            ->with('tag')
            ->latest()
            ->paginate(20);

        return FormResource::collection($forms);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->typedUser($request)->tenant_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'isActive' => ['boolean'],
            'isLandingPage' => ['boolean'],
            'landingTitle' => ['nullable', 'string', 'max:255'],
            'successMessage' => ['nullable', 'string'],
            'redirectUrl' => ['nullable', 'url', 'max:500'],
            'slug' => ['nullable', 'string', 'max:100', Rule::unique('forms', 'slug')->where('tenant_id', $tenantId)],
            'tagId' => ['nullable', 'integer', 'exists:tags,id'],
        ]);

        $form = Form::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']).'-'.Str::random(6),
            'description' => $data['description'] ?? null,
            'is_active' => $data['isActive'] ?? true,
            'is_landing_page' => $data['isLandingPage'] ?? false,
            'landing_title' => $data['landingTitle'] ?? null,
            'success_message' => $data['successMessage'] ?? null,
            'redirect_url' => $data['redirectUrl'] ?? null,
            'tag_id' => $data['tagId'] ?? null,
        ]);

        return (new FormResource($form->load(['fields', 'tag'])))->response()->setStatusCode(201);
    }

    public function show(Request $request, string $ulid): JsonResponse
    {
        $form = Form::where('tenant_id', $this->typedUser($request)->tenant_id)
            ->where('ulid', $ulid)
            ->withCount('submissions')
            ->with(['fields', 'tag'])
            ->firstOrFail();

        return (new FormResource($form))->response();
    }

    public function update(Request $request, string $ulid): JsonResponse
    {
        $tenantId = $this->typedUser($request)->tenant_id;

        $form = Form::where('tenant_id', $tenantId)->where('ulid', $ulid)->firstOrFail();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'isActive' => ['sometimes', 'boolean'],
            'isLandingPage' => ['sometimes', 'boolean'],
            'landingTitle' => ['nullable', 'string', 'max:255'],
            'successMessage' => ['nullable', 'string'],
            'redirectUrl' => ['nullable', 'url', 'max:500'],
            'slug' => ['sometimes', 'string', 'max:100', Rule::unique('forms', 'slug')->where('tenant_id', $tenantId)->ignore($form->id)],
            'tagId' => ['sometimes', 'nullable', 'integer', 'exists:tags,id'],
        ]);

        $form->update([
            'name' => $data['name'] ?? $form->name,
            'slug' => $data['slug'] ?? $form->slug,
            'description' => $data['description'] ?? $form->description,
            'is_active' => $data['isActive'] ?? $form->is_active,
            'is_landing_page' => $data['isLandingPage'] ?? $form->is_landing_page,
            'landing_title' => $data['landingTitle'] ?? $form->landing_title,
            'success_message' => $data['successMessage'] ?? $form->success_message,
            'redirect_url' => $data['redirectUrl'] ?? $form->redirect_url,
            'tag_id' => array_key_exists('tagId', $data) ? $data['tagId'] : $form->tag_id,
        ]);

        return (new FormResource($form->load(['fields', 'tag'])))->response();
    }

    public function destroy(Request $request, string $ulid): JsonResponse
    {
        $form = Form::where('tenant_id', $this->typedUser($request)->tenant_id)
            ->where('ulid', $ulid)
            ->firstOrFail();
        $form->delete();

        return response()->json(null, 204);
    }

    /**
     * Replace all fields of a form atomically.
     *
     * @param array<int, array<string, mixed>> $fields
     */
    public function updateFields(Request $request, string $ulid): JsonResponse
    {
        $form = Form::where('tenant_id', $this->typedUser($request)->tenant_id)
            ->where('ulid', $ulid)
            ->firstOrFail();

        $request->validate([
            'fields' => ['required', 'array'],
            'fields.*.type' => ['required', 'string'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.name' => ['required', 'string', 'max:100'],
            'fields.*.isRequired' => ['boolean'],
            'fields.*.mapsTo' => ['nullable', 'string', 'max:100'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:255'],
            'fields.*.helpText' => ['nullable', 'string', 'max:500'],
            'fields.*.defaultValue' => ['nullable', 'string', 'max:500'],
            'fields.*.options' => ['nullable', 'array'],
        ]);

        $form->fields()->delete();

        foreach ($request->input('fields', []) as $i => $f) {
            FormField::create([
                'form_id' => $form->id,
                'type' => $f['type'],
                'label' => $f['label'],
                'name' => $f['name'],
                'placeholder' => $f['placeholder'] ?? null,
                'help_text' => $f['helpText'] ?? null,
                'default_value' => $f['defaultValue'] ?? null,
                'is_required' => $f['isRequired'] ?? false,
                'maps_to' => $f['mapsTo'] ?? null,
                'options' => $f['options'] ?? null,
                'sort_order' => $i,
            ]);
        }

        return (new FormResource($form->load(['fields', 'tag'])))->response();
    }

    public function submissions(Request $request, string $ulid): AnonymousResourceCollection
    {
        $form = Form::where('tenant_id', $this->typedUser($request)->tenant_id)
            ->where('ulid', $ulid)
            ->firstOrFail();

        $submissions = $form->submissions()
            ->with('contact', 'values')
            ->latest('submitted_at')
            ->paginate(30);

        return FormSubmissionResource::collection($submissions);
    }

    public function embedCode(Request $request, string $ulid): JsonResponse
    {
        $form = Form::where('tenant_id', $this->typedUser($request)->tenant_id)
            ->where('ulid', $ulid)
            ->firstOrFail();

        $baseUrl = rtrim(config('app.url'), '/');

        $iframe = '<iframe src="'.$baseUrl.'/f/'.$form->slug.'" width="100%" height="600" frameborder="0" style="border:none;overflow:hidden" scrolling="no" allowtransparency="true"></iframe>';

        $script = <<<HTML
<!-- SGP Form: {$form->name} -->
<div id="sgp-form-{$form->slug}"></div>
<script>
(function(){
  var d=document,s=d.createElement('script');
  s.src='{$baseUrl}/form-embed.js';
  s.setAttribute('data-form','{$form->slug}');
  s.setAttribute('data-container','sgp-form-{$form->slug}');
  s.setAttribute('data-base','{$baseUrl}');
  s.async=true;
  d.head.appendChild(s);
})();
</script>
HTML;

        return response()->json([
            'iframe' => $iframe,
            'script' => $script,
        ]);
    }
}
