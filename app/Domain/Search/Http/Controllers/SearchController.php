<?php

namespace App\Domain\Search\Http\Controllers;

use App\Domain\Search\Services\SearchService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __construct(private readonly SearchService $search) {}

    /** JSON endpoint used by the ⌘K command palette. */
    public function query(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'types' => ['nullable', 'array'],
            'types.*' => ['string', 'in:'.implode(',', array_keys(SearchService::TYPES))],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json([
            'results' => $this->search->search($data['q'] ?? '', $request->user(), $data['types'] ?? [], $data['limit'] ?? 30),
        ]);
    }

    /** Full-page search results grouped by entity type. */
    public function index(Request $request): Response
    {
        $q = (string) $request->string('q');
        $type = $request->string('type')->toString() ?: null;
        $types = $type && isset(SearchService::TYPES[$type]) ? [$type] : [];

        return Inertia::render('Search/Index', [
            'q' => $q,
            'type' => $types[0] ?? null,
            'results' => $q !== '' ? $this->search->search($q, $request->user(), $types, 100) : [],
            'types' => collect(SearchService::TYPES)
                ->filter(fn ($meta) => $request->user()->hasPermission($meta[1]))
                ->map(fn ($meta, $key) => ['value' => $key, 'label' => $meta[0]])
                ->values(),
        ]);
    }
}
