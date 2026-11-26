<?php

namespace App\Domain\CustomFields\Http\Controllers;

use App\Domain\CustomFields\Models\CustomFieldDefinition;
use App\Domain\CustomFields\Models\CustomFieldValue;
use App\Domain\CustomFields\Services\CustomFieldService;
use App\Domain\Search\Services\SearchService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Supporting endpoints for custom field inputs: reference lookups and file uploads. */
class CustomFieldInputController extends Controller
{
    /** Options for user / asset / relation pickers. */
    public function lookup(Request $request, SearchService $search): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:user,'.implode(',', array_keys(SearchService::TYPES))],
            'q' => ['nullable', 'string', 'max:120'],
        ]);
        $q = trim($data['q'] ?? '');

        if ($data['type'] === 'user') {
            $users = User::query()
                ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                    ->where('name', 'like', '%'.addcslashes($q, '%_\\').'%')
                    ->orWhere('email', 'like', '%'.addcslashes($q, '%_\\').'%')))
                ->orderBy('name')->limit(20)->get(['id', 'name', 'email']);

            return response()->json($users->map(fn ($u) => ['value' => $u->id, 'label' => $u->name, 'description' => $u->email]));
        }

        if ($q === '') {
            return response()->json([]);
        }

        return response()->json(collect($search->search($q, $request->user(), [$data['type']], 20))
            ->map(fn ($r) => ['value' => $r['id'], 'label' => $r['title'], 'description' => $r['subtitle']])
            ->values());
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,png,jpg,jpeg,gif,webp,txt,csv,xlsx,xls,docx,doc,zip'],
        ]);
        $file = $request->file('file');
        $path = $file->storeAs(
            CustomFieldService::FILE_DIRECTORY.'/'.now()->format('Y/m'),
            Str::uuid().'.'.strtolower($file->getClientOriginalExtension()),
            'local',
        );

        return response()->json([
            'path' => $path,
            'name' => Str::limit(basename($file->getClientOriginalName()), 180, ''),
            'size' => $file->getSize(),
        ]);
    }

    /** Download a file that is referenced by a stored custom field value. */
    public function download(Request $request): StreamedResponse
    {
        $data = $request->validate(['path' => ['required', 'string', 'max:255']]);
        $path = $data['path'];
        abort_unless(str_starts_with($path, CustomFieldService::FILE_DIRECTORY.'/') && ! str_contains($path, '..'), 404);

        $value = CustomFieldValue::query()
            ->whereIn('definition_id', CustomFieldDefinition::query()->where('type', 'file')->select('id'))
            ->where('value_json->path', $path)
            ->first();
        abort_unless($value && Storage::disk('local')->exists($path), 404);

        $view = $this->viewPermission($value->entity_type);
        abort_unless($view === null || $request->user()->can($view), 403);

        return Storage::disk('local')->download($path, $value->value_json['name'] ?? basename($path));
    }

    private function viewPermission(string $entity): ?string
    {
        return SearchService::TYPES[$entity][1] ?? null;
    }
}
