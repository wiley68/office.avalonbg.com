<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ContactController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'q' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'in:id,last_name,name,firm'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $query = Contact::query()->with(['citi', 'dlazhnost']);

        if (! empty($validated['q'])) {
            $search = trim((string) $validated['q']);
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('second_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('firm', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('gsm_1_m', 'like', "%{$search}%");
            });
        }

        $sortColumn = $validated['sort'] ?? 'id';
        $sortDirection = $validated['direction'] ?? 'desc';

        $query
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('id', 'desc');

        $perPage = (int) ($validated['per_page'] ?? 20);

        return ContactResource::collection(
            $query->paginate($perPage)->withQueryString()
        );
    }

    public function store(StoreContactRequest $request): ContactResource
    {
        $contact = Contact::query()->create($request->validated());

        return new ContactResource($contact->load(['citi', 'dlazhnost']));
    }

    public function show(int $contact): ContactResource
    {
        $row = Contact::query()->with(['citi', 'dlazhnost'])->findOrFail($contact);

        return new ContactResource($row);
    }

    public function update(UpdateContactRequest $request, int $contact): ContactResource
    {
        $row = Contact::query()->findOrFail($contact);
        $row->update($request->validated());

        return new ContactResource($row->fresh()->load(['citi', 'dlazhnost']));
    }

    public function destroy(int $contact): Response
    {
        $row = Contact::query()->findOrFail($contact);
        $row->delete();

        return response()->noContent();
    }
}
