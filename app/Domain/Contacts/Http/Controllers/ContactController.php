<?php

namespace App\Domain\Contacts\Http\Controllers;

use App\Domain\Contacts\Models\Contact;
use App\Domain\Contacts\Services\ContactService;
use App\Domain\CustomFields\Services\CustomFieldService;
use App\Domain\Inventory\Services\HistoryPresenter;
use App\Domain\Jobs\Models\ProductionJob;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactController extends Controller
{
    public const TYPES = ['person', 'organization'];

    private const SORTS = ['name', 'email', 'type', 'created_at'];

    public function __construct(
        private readonly CustomFieldService $customFields,
        private readonly ContactService $contacts,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('contacts.contacts.view');
        $filters = $this->filters($request);

        $contacts = $this->query($filters)
            ->with('organization:id,name')
            ->withCount(['jobs', 'members'])
            ->paginate(25)
            ->withQueryString();
        $values = $this->customFields->valuesForMany('contact', $contacts->getCollection()->pluck('id')->all());

        return Inertia::render('Contacts/Index', [
            'contacts' => $contacts->through(fn (Contact $c) => [
                'id' => $c->id,
                'type' => $c->type,
                'name' => $c->name,
                'email' => $c->email,
                'phone' => $c->phone,
                'title' => $c->title,
                'organization' => $c->organization?->only(['id', 'name']),
                'jobs_count' => $c->jobs_count,
                'members_count' => $c->members_count,
                'custom_fields' => $values[$c->id] ?? (object) [],
            ]),
            'filters' => $filters,
            'customFieldColumns' => $this->customFields->listColumns('contact'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('contacts.contacts.export');
        $query = $this->query($this->filters($request))->with('organization:id,name');
        $safe = fn ($v) => is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$v : $v;

        return response()->streamDownload(function () use ($query, $safe) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Type', 'Name', 'Organization', 'Title', 'Email', 'Phone', 'Address']);
            $query->chunk(500, function ($rows) use ($out, $safe) {
                foreach ($rows as $c) {
                    fputcsv($out, array_map($safe, [$c->id, $c->type, $c->name, $c->organization?->name, $c->title, $c->email, $c->phone, $c->address]));
                }
            });
            fclose($out);
        }, 'contacts-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function lookup(Request $request): JsonResponse
    {
        Gate::authorize('contacts.contacts.view');
        $data = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'type' => ['nullable', Rule::in(self::TYPES)]]);
        $q = trim($data['q'] ?? '');

        $rows = Contact::query()
            ->when($q !== '', fn ($query) => $this->applySearch($query, $q))
            ->when($data['type'] ?? null, fn ($query, $t) => $query->where('type', $t))
            ->orderBy('name')->limit(20)->get(['id', 'name', 'email', 'type']);

        return response()->json($rows->map(fn (Contact $c) => [
            'value' => $c->id,
            'label' => $c->name,
            'description' => trim(implode(' · ', array_filter([ucfirst($c->type), $c->email]))),
        ]));
    }

    public function create(Request $request): Response
    {
        Gate::authorize('contacts.contacts.create');
        $org = $request->integer('organization') ? Contact::query()->where('type', 'organization')->find($request->integer('organization')) : null;

        return Inertia::render('Contacts/Form', $this->formProps(null) + [
            'defaults' => [
                'type' => $request->query('type') === 'organization' ? 'organization' : 'person',
                'organization' => $org ? ['value' => $org->id, 'label' => $org->name] : null,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('contacts.contacts.create');
        $data = $this->validated($request);
        $clean = $this->customFields->validate('contact', $request->input('custom_fields', []) ?? [], partial: false);
        $contact = $this->contacts->save(new Contact, $data, $clean);

        return redirect()->route('contacts.show', $contact)->with('success', "Contact “{$contact->name}” created.");
    }

    public function show(Contact $contact, HistoryPresenter $history): Response
    {
        Gate::authorize('contacts.contacts.view');
        $contact->load('organization:id,name');

        $jobLink = fn (ProductionJob $j, ?string $role) => [
            'id' => $j->id,
            'number' => $j->number,
            'name' => $j->name,
            'stage' => $j->stage?->value,
            'stage_label' => $j->stage?->label(),
            'starts_at' => $j->starts_at?->toIso8601String(),
            'ends_at' => $j->ends_at?->toIso8601String(),
            'role' => $role,
        ];

        $clientJobs = $contact->jobs()->latest('starts_at')->limit(50)->get()->map(fn ($j) => $jobLink($j, 'Client'));
        $linked = DB::table('job_contacts')->where('contact_id', $contact->id)->pluck('role', 'job_id');
        $linkedJobs = $linked->isEmpty() ? collect() : ProductionJob::query()->whereIn('id', $linked->keys())->latest('starts_at')->limit(50)->get()
            ->map(fn ($j) => $jobLink($j, $linked[$j->id] ?: 'Contact'));

        $jobs = $clientJobs->concat($linkedJobs)->unique('id')->sortByDesc('starts_at')->values();

        $members = $contact->members()->orderBy('name')->limit(200)->get(['id', 'name', 'email', 'phone', 'title'])
            ->map(fn (Contact $m) => $m->only(['id', 'name', 'email', 'phone', 'title']));

        return Inertia::render('Contacts/Show', [
            'contact' => $this->present($contact) + ['organization' => $contact->organization?->only(['id', 'name'])],
            'members' => $members,
            'jobs' => $jobs,
            'history' => $history->for($contact),
            'customFieldSchema' => $this->customFields->schema('contact'),
            'customFields' => (object) $contact->customFields(),
        ]);
    }

    public function edit(Contact $contact): Response
    {
        Gate::authorize('contacts.contacts.edit');
        $contact->load('organization:id,name');

        return Inertia::render('Contacts/Form', $this->formProps($contact) + [
            'defaults' => ['type' => $contact->type, 'organization' => $contact->organization ? ['value' => $contact->organization->id, 'label' => $contact->organization->name] : null],
        ]);
    }

    public function update(Request $request, Contact $contact): RedirectResponse
    {
        Gate::authorize('contacts.contacts.edit');
        $data = $this->validated($request, $contact);
        if ($data['type'] === 'person' && $contact->type === 'organization' && $contact->members()->exists()) {
            return back()->withErrors(['type' => 'This organization has people linked to it; unlink them before changing its type.']);
        }
        $clean = $this->customFields->validate('contact', $request->input('custom_fields', []) ?? [], partial: false);
        $this->contacts->save($contact, $data, $clean);

        return redirect()->route('contacts.show', $contact)->with('success', 'Contact updated.');
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        Gate::authorize('contacts.contacts.delete');
        if ($reason = $this->contacts->deletionBlocker($contact)) {
            return back()->with('error', $reason);
        }
        $this->contacts->delete($contact);

        return redirect()->route('contacts.index')->with('success', "Contact “{$contact->name}” deleted.");
    }

    private function validated(Request $request, ?Contact $contact = null): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(self::TYPES)],
            'name' => ['required', 'string', 'max:255'],
            'organization_id' => [
                'nullable', 'integer',
                Rule::exists('contacts', 'id')->where('type', 'organization')->whereNull('deleted_at'),
                ...($contact ? [Rule::notIn([$contact->id])] : []),
            ],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64', 'regex:/^[0-9+().\-\s\/extEXT#]+$/'],
            'title' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:20000'],
            'custom_fields' => ['nullable', 'array'],
        ], [
            'organization_id.exists' => 'Choose an existing organization.',
            'phone.regex' => 'Enter a valid phone number.',
        ]);
        unset($data['custom_fields']);

        return $data;
    }

    private function filters(Request $request): array
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', Rule::in(self::TYPES)],
            'sort' => ['nullable', Rule::in(self::SORTS)],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        return [
            'search' => $data['search'] ?? '',
            'type' => $data['type'] ?? null,
            'sort' => $data['sort'] ?? 'name',
            'direction' => $data['direction'] ?? 'asc',
        ];
    }

    private function query(array $filters): Builder
    {
        return Contact::query()
            ->when($filters['search'] !== '', fn ($q) => $this->applySearch($q, $filters['search']))
            ->when($filters['type'], fn ($q, $t) => $q->where('type', $t))
            ->orderBy($filters['sort'], $filters['direction'])
            ->orderBy('id');
    }

    private function applySearch(Builder $query, string $term): Builder
    {
        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(fn ($q) => $q
            ->where('name', 'like', $like)
            ->orWhere('email', 'like', $like)
            ->orWhere('phone', 'like', $like)
            ->orWhere('title', 'like', $like));
    }

    private function present(Contact $contact): array
    {
        return [
            'id' => $contact->id,
            'type' => $contact->type,
            'name' => $contact->name,
            'organization_id' => $contact->organization_id,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'title' => $contact->title,
            'address' => $contact->address,
            'notes' => $contact->notes,
            'created_at' => $contact->created_at?->toIso8601String(),
        ];
    }

    private function formProps(?Contact $contact): array
    {
        return [
            'contact' => $contact ? $this->present($contact) : null,
            'types' => array_map(fn ($t) => ['value' => $t, 'label' => ucfirst($t)], self::TYPES),
            'customFieldSchema' => $this->customFields->schema('contact'),
            'customFields' => (object) ($contact?->customFields() ?? []),
        ];
    }
}
