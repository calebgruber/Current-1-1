<?php

namespace App\Domain\Contacts\Services;

use App\Domain\Contacts\Models\Contact;
use App\Domain\CustomFields\Services\CustomFieldService;
use Illuminate\Support\Facades\DB;

class ContactService
{
    public function __construct(private readonly CustomFieldService $customFields) {}

    /**
     * @param  array<string, mixed>  $data  validated attributes
     * @param  array<string, mixed>  $customFields  values already validated by CustomFieldService
     */
    public function save(Contact $contact, array $data, array $customFields = []): Contact
    {
        return DB::transaction(function () use ($contact, $data, $customFields) {
            if (($data['type'] ?? $contact->type) === 'organization') {
                $data['organization_id'] = null;
            }
            $contact->fill($data)->save();
            $this->customFields->save($contact, $customFields);

            return $contact;
        });
    }

    /** Whether the contact can be deleted safely (no jobs reference it). */
    public function deletionBlocker(Contact $contact): ?string
    {
        if ($contact->jobs()->exists()) {
            return 'This contact is the client on one or more jobs.';
        }

        return null;
    }

    public function delete(Contact $contact): void
    {
        DB::transaction(function () use ($contact) {
            Contact::query()->where('organization_id', $contact->id)->update(['organization_id' => null]);
            $contact->delete();
        });
    }
}
