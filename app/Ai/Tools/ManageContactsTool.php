<?php

namespace App\Ai\Tools;

use App\Http\Resources\ContactResource;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class ManageContactsTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Управление на контакти от service базата: list, count, show, create, update, delete.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return $this->encode(['error' => 'Няма логнат потребител.']);
        }

        try {
            $input = $this->normalizedInput($request);
            $action = (string) ($input['action'] ?? '');

            return match ($action) {
                'list' => $this->listContacts($input),
                'list_without_cards' => $this->listContactsWithoutCards($input),
                'count_without_cards' => $this->countContactsWithoutCards($input),
                'count' => $this->countContacts(),
                'show' => $this->showContact($input),
                'create' => $this->createContact($input),
                'update' => $this->updateContact($input),
                'delete' => $this->deleteContact($input),
                default => $this->encode(['error' => 'Невалидна стойност за action.']),
            };
        } catch (Throwable $e) {
            return $this->encode([
                'error' => 'Грешка при обработка на contacts tool.',
                'details' => $e->getMessage(),
            ]);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->enum(['list', 'list_without_cards', 'count_without_cards', 'count', 'show', 'create', 'update', 'delete'])
                ->description('Операция с контакти.')
                ->required(),
            'id' => $schema->integer()->description('ID на контакт.'),
            'q' => $schema->string()->description('Търсене при list.'),
            'limit' => $schema->integer()->description('Брой редове за list (по подразбиране 50, максимум 5000).'),
            'page' => $schema->integer()->description('Номер на страница за list (по подразбиране 1).'),
            'per_page' => $schema->integer()->description('Редове на страница за list (1-5000, по подразбиране 50).'),
            'offset' => $schema->integer()->description('Offset за list (ако е зададен, има приоритет над page).'),
            'include_card_counts' => $schema->boolean()->description('Само за list_without_cards: включва броя карти (обикновено 0/0).'),
            'citi_id' => $schema->integer()->description('ID на населено място (citi_id).'),
            'last_name' => $schema->string()->description('Фамилия (задължителна при create).'),
            'name' => $schema->string()->description('Собствено име.'),
            'second_name' => $schema->string()->description('Бащино име.'),
            'firm' => $schema->string()->description('Фирма.'),
            'email' => $schema->string()->description('Имейл.'),
            'gsm_1_m' => $schema->string()->description('Основен GSM.'),
            'note' => $schema->string()->description('Бележка за контакта.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function listContacts(array $input): string
    {
        $perPage = max(
            1,
            min(
                (int) ($input['per_page'] ?? $input['limit'] ?? 50),
                5000
            ),
        );
        $page = max(1, (int) ($input['page'] ?? 1));
        $offset = array_key_exists('offset', $input)
            ? max(0, (int) $input['offset'])
            : (($page - 1) * $perPage);
        $query = Contact::query()->with(['citi', 'dlazhnost'])->latest('id');

        if (! empty($input['q']) && is_string($input['q'])) {
            $search = trim($input['q']);
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

        $total = (clone $query)->count();
        $rows = $query->offset($offset)->limit($perPage)->get();
        $currentPage = intdiv($offset, $perPage) + 1;
        $lastPage = max(1, (int) ceil($total / $perPage));

        return $this->encode(
            [
                'total' => $total,
                'returned' => $rows->count(),
                'page' => $currentPage,
                'per_page' => $perPage,
                'last_page' => $lastPage,
                'offset' => $offset,
                'data' => ContactResource::collection($rows)->resolve(),
            ],
        );
    }

    private function countContacts(): string
    {
        return $this->encode([
            'total' => Contact::query()->count(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function countContactsWithoutCards(array $input): string
    {
        $query = Contact::query()
            ->whereNotExists(function ($sub): void {
                $sub->select(DB::raw(1))
                    ->from('varanty')
                    ->whereColumn('varanty.client_id', 'contacts.id');
            })
            ->whereNotExists(function ($sub): void {
                $sub->select(DB::raw(1))
                    ->from('projects')
                    ->whereColumn('projects.name', 'contacts.id');
            });

        if (! empty($input['q']) && is_string($input['q'])) {
            $search = trim($input['q']);
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('second_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('firm', 'like', "%{$search}%")
                    ->orWhere('gsm_1_m', 'like', "%{$search}%")
                    ->orWhere('tel1', 'like', "%{$search}%")
                    ->orWhere('b_phone', 'like', "%{$search}%");
            });
        }

        return $this->encode([
            'total' => $query->count(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function listContactsWithoutCards(array $input): string
    {
        $query = Contact::query()
            ->select([
                'id',
                'name',
                'second_name',
                'last_name',
                'firm',
                'gsm_1_m',
                'tel1',
                'b_phone',
            ])
            ->whereNotExists(function ($sub): void {
                $sub->select(DB::raw(1))
                    ->from('varanty')
                    ->whereColumn('varanty.client_id', 'contacts.id');
            })
            ->whereNotExists(function ($sub): void {
                $sub->select(DB::raw(1))
                    ->from('projects')
                    ->whereColumn('projects.name', 'contacts.id');
            });

        if (! empty($input['q']) && is_string($input['q'])) {
            $search = trim($input['q']);
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('second_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('firm', 'like', "%{$search}%")
                    ->orWhere('gsm_1_m', 'like', "%{$search}%")
                    ->orWhere('tel1', 'like', "%{$search}%")
                    ->orWhere('b_phone', 'like', "%{$search}%");
            });
        }

        $rows = $query
            ->orderBy('last_name')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $includeCardCounts = (bool) ($input['include_card_counts'] ?? false);
        $data = $rows->map(static function (Contact $row) use ($includeCardCounts): array {
            $fullName = collect([$row->name, $row->second_name, $row->last_name])
                ->filter(static fn ($v): bool => is_string($v) && trim($v) !== '')
                ->map(static fn ($v): string => trim((string) $v))
                ->implode(' ');

            $primaryPhone = collect([$row->gsm_1_m, $row->tel1, $row->b_phone])
                ->first(static fn ($v): bool => is_string($v) && trim($v) !== '');

            $result = [
                'id' => $row->id,
                'name' => $row->name,
                'second_name' => $row->second_name,
                'last_name' => $row->last_name,
                'full_name' => $fullName !== '' ? $fullName : null,
                'firm' => $row->firm,
                'phone' => is_string($primaryPhone) ? trim($primaryPhone) : null,
                'phones' => [
                    'gsm_1_m' => $row->gsm_1_m,
                    'tel1' => $row->tel1,
                    'b_phone' => $row->b_phone,
                ],
            ];

            if ($includeCardCounts) {
                $result['warranty_cards_count'] = 0;
                $result['service_cards_count'] = 0;
            }

            return $result;
        })->values()->all();

        return $this->encode([
            'total' => count($data),
            'returned' => count($data),
            'data' => $data,
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function showContact(array $input): string
    {
        $v = Validator::make($input, [
            'id' => ['required', 'integer', 'exists:service.contacts,id'],
        ]);

        if ($v->fails()) {
            return $this->encode(['error' => $v->errors()->first()]);
        }

        $row = Contact::query()->with(['citi', 'dlazhnost'])->findOrFail((int) $input['id']);

        return $this->encode((new ContactResource($row))->resolve());
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function createContact(array $input): string
    {
        $v = Validator::make($input, [
            'citi_id' => ['required', 'integer'],
            'last_name' => ['required', 'string', 'max:24'],
            'name' => ['nullable', 'string', 'max:24'],
            'second_name' => ['nullable', 'string', 'max:24'],
            'firm' => ['nullable', 'string', 'max:256'],
            'email' => ['nullable', 'string', 'max:45'],
            'gsm_1_m' => ['nullable', 'string', 'max:128'],
            'note' => ['nullable', 'string'],
        ]);

        if ($v->fails()) {
            return $this->encode(['error' => $v->errors()->first()]);
        }

        $row = Contact::query()->create($v->validated());

        return $this->encode((new ContactResource($row->fresh()->load(['citi', 'dlazhnost'])))->resolve());
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function updateContact(array $input): string
    {
        $v = Validator::make($input, [
            'id' => ['required', 'integer', 'exists:service.contacts,id'],
            'citi_id' => ['sometimes', 'required', 'integer'],
            'last_name' => ['sometimes', 'required', 'string', 'max:24'],
            'name' => ['sometimes', 'nullable', 'string', 'max:24'],
            'second_name' => ['sometimes', 'nullable', 'string', 'max:24'],
            'firm' => ['sometimes', 'nullable', 'string', 'max:256'],
            'email' => ['sometimes', 'nullable', 'string', 'max:45'],
            'gsm_1_m' => ['sometimes', 'nullable', 'string', 'max:128'],
            'note' => ['sometimes', 'nullable', 'string'],
        ]);

        if ($v->fails()) {
            return $this->encode(['error' => $v->errors()->first()]);
        }

        $row = Contact::query()->findOrFail((int) $input['id']);
        $row->update(collect($v->validated())->except('id')->all());

        return $this->encode((new ContactResource($row->fresh()->load(['citi', 'dlazhnost'])))->resolve());
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function deleteContact(array $input): string
    {
        $v = Validator::make($input, [
            'id' => ['required', 'integer', 'exists:service.contacts,id'],
        ]);

        if ($v->fails()) {
            return $this->encode(['error' => $v->errors()->first()]);
        }

        $id = (int) $input['id'];
        Contact::query()->findOrFail($id)->delete();

        return $this->encode(['ok' => true, 'id' => $id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedInput(Request $request): array
    {
        $input = $request->all();

        if (
            isset($input['schema_definition'])
            && is_array($input['schema_definition'])
        ) {
            return $input['schema_definition'];
        }

        return $input;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encode(array $payload): string
    {
        return json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE
                | JSON_PRETTY_PRINT
                | JSON_INVALID_UTF8_SUBSTITUTE
        ) ?: '{"error":"JSON encode failed"}';
    }
}
