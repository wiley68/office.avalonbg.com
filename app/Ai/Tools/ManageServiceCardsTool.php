<?php

namespace App\Ai\Tools;

use App\Http\Resources\ServiceCardResource;
use App\Models\ServiceCard;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class ManageServiceCardsTool implements Tool
{
    /** @var list<string> */
    private const SPECIAL_VALUES = ['Спешна поръчка', 'Нормална поръчка'];

    /** @var list<string> */
    private const VARANTY_VALUES = ['Гаранционен', 'Извън гаранционен'];

    /** @var list<string> */
    private const ETAP_VALUES = [
        'Приета за сервиз',
        'Диагностика',
        'Извършва се ремонта',
        'Изпратен за гаранционен ремонт',
        'Приключен ремонт',
    ];

    public function description(): Stringable|string
    {
        return 'Управление на сервизни карти (projects, service DB): list, count, show, create, update, delete. '
            .'Връзки: name->contacts.id, rakovoditel_id/serviseproblemtechnik_id/saobshtilclient_id -> members.id.';
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
                'list' => $this->listCards($input),
                'count' => $this->countCards(),
                'show' => $this->showCard($input),
                'create' => $this->createCard($input),
                'update' => $this->updateCard($input),
                'delete' => $this->deleteCard($input),
                default => $this->encode(['error' => 'Невалидна стойност за action.']),
            };
        } catch (Throwable $e) {
            return $this->encode([
                'error' => 'Грешка при обработка на service cards tool.',
                'details' => $e->getMessage(),
            ]);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->enum(['list', 'count', 'show', 'create', 'update', 'delete'])
                ->description('Операция със сервизни карти.')
                ->required(),
            'id' => $schema->integer()->description('ID на сервизна карта (projects.id).'),
            'q' => $schema->string()->description('Търсене в list (продукт, проблем, контакт, техник).'),
            'limit' => $schema->integer()->description('Брой редове за list (по подразбиране 50, максимум 200).'),
            'page' => $schema->integer()->description('Номер на страница за list.'),
            'per_page' => $schema->integer()->description('Редове на страница за list (1–200).'),
            'offset' => $schema->integer()->description('Offset за list (ако е зададен, има приоритет над page).'),
            'rakovoditel_id' => $schema->integer()->description('ID на ръководител (members.id), optional.'),
            'datecard' => $schema->string()->description('Дата на създаване/приемане (ISO или Y-m-d H:i:s).'),
            'name' => $schema->integer()->description('ID на контакт (contacts.id).'),
            'special' => $schema->string()->description('Спешност: „Спешна поръчка“ или „Нормална поръчка“.'),
            'product' => $schema->string()->description('Продукт/устройство.'),
            'varanty' => $schema->string()->description('Статус гаранция: „Гаранционен“ / „Извън гаранционен“.'),
            'problem' => $schema->string()->description('Проблем по описание на клиента.'),
            'serviseproblem' => $schema->string()->description('Диагностициран сервизен проблем.'),
            'serviseproblemtechnik_id' => $schema->integer()->description('Техник, установил проблема (members.id).'),
            'dopclient' => $schema->string()->description('Допълнително към клиента.'),
            'datepredavane' => $schema->string()->description('Дата предаване/приключване (ISO или Y-m-d H:i:s).'),
            'saobshtilclient_id' => $schema->integer()->description('Кой е съобщил на клиента (members.id).'),
            'clientopisanie' => $schema->string()->description('Кратко клиентско описание.'),
            'etap' => $schema->string()->description('Етап на ремонта.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function listCards(array $input): string
    {
        $perPage = max(1, min((int) ($input['per_page'] ?? $input['limit'] ?? 50), 200));
        $page = max(1, (int) ($input['page'] ?? 1));
        $offset = array_key_exists('offset', $input)
            ? max(0, (int) $input['offset'])
            : (($page - 1) * $perPage);

        $query = ServiceCard::query()
            ->with(['contact', 'rakovoditel', 'serviseproblemtechnik', 'saobshtilclient'])
            ->latest('id');

        if (! empty($input['q']) && is_string($input['q'])) {
            $search = trim($input['q']);
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('product', 'like', "%{$search}%")
                    ->orWhere('problem', 'like', "%{$search}%")
                    ->orWhere('serviseproblem', 'like', "%{$search}%")
                    ->orWhere('clientopisanie', 'like', "%{$search}%")
                    ->orWhereHas('contact', function ($q) use ($search): void {
                        $q
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('second_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('firm', 'like', "%{$search}%");
                    })
                    ->orWhereHas('serviseproblemtechnik', fn ($q) => $q->where('username', 'like', "%{$search}%"))
                    ->orWhereHas('saobshtilclient', fn ($q) => $q->where('username', 'like', "%{$search}%"));
            });
        }

        $total = (clone $query)->count();
        $rows = $query->offset($offset)->limit($perPage)->get();
        $currentPage = intdiv($offset, $perPage) + 1;
        $lastPage = max(1, (int) ceil($total / $perPage));

        return $this->encode([
            'total' => $total,
            'returned' => $rows->count(),
            'page' => $currentPage,
            'per_page' => $perPage,
            'last_page' => $lastPage,
            'offset' => $offset,
            'data' => ServiceCardResource::collection($rows)->resolve(),
        ]);
    }

    private function countCards(): string
    {
        return $this->encode([
            'total' => ServiceCard::query()->count(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function showCard(array $input): string
    {
        $v = Validator::make($input, [
            'id' => ['required', 'integer', Rule::exists('service.projects', 'id')],
        ]);

        if ($v->fails()) {
            return $this->encode(['error' => $v->errors()->first()]);
        }

        $row = ServiceCard::query()
            ->with(['contact', 'rakovoditel', 'serviseproblemtechnik', 'saobshtilclient'])
            ->findOrFail((int) $input['id']);

        return $this->encode((new ServiceCardResource($row))->resolve());
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function createCard(array $input): string
    {
        $v = Validator::make($input, $this->fieldRules(true));

        if ($v->fails()) {
            return $this->encode(['error' => $v->errors()->first()]);
        }

        $row = ServiceCard::query()->create($v->validated());

        return $this->encode((new ServiceCardResource(
            $row->fresh()->load(['contact', 'rakovoditel', 'serviseproblemtechnik', 'saobshtilclient'])
        ))->resolve());
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function updateCard(array $input): string
    {
        $v = Validator::make($input, array_merge(
            ['id' => ['required', 'integer', Rule::exists('service.projects', 'id')]],
            $this->fieldRules(false),
        ));

        if ($v->fails()) {
            return $this->encode(['error' => $v->errors()->first()]);
        }

        $validated = $v->validated();
        $id = (int) $validated['id'];
        unset($validated['id']);

        $row = ServiceCard::query()->findOrFail($id);
        $row->update($validated);

        return $this->encode((new ServiceCardResource(
            $row->fresh()->load(['contact', 'rakovoditel', 'serviseproblemtechnik', 'saobshtilclient'])
        ))->resolve());
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function deleteCard(array $input): string
    {
        $v = Validator::make($input, [
            'id' => ['required', 'integer', Rule::exists('service.projects', 'id')],
        ]);

        if ($v->fails()) {
            return $this->encode(['error' => $v->errors()->first()]);
        }

        $id = (int) $input['id'];
        ServiceCard::query()->findOrFail($id)->delete();

        return $this->encode(['ok' => true, 'id' => $id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldRules(bool $forCreate): array
    {
        return [
            'rakovoditel_id' => $forCreate
                ? ['nullable', 'integer', Rule::exists('service.members', 'id')]
                : ['sometimes', 'nullable', 'integer', Rule::exists('service.members', 'id')],
            'datecard' => $forCreate ? ['required', 'date'] : ['sometimes', 'date'],
            'name' => $forCreate
                ? ['required', 'integer', Rule::exists('service.contacts', 'id')]
                : ['sometimes', 'integer', Rule::exists('service.contacts', 'id')],
            'special' => $forCreate
                ? ['required', 'string', Rule::in(self::SPECIAL_VALUES)]
                : ['sometimes', 'string', Rule::in(self::SPECIAL_VALUES)],
            'product' => $forCreate ? ['required', 'string', 'max:128'] : ['sometimes', 'string', 'max:128'],
            'varanty' => $forCreate
                ? ['required', 'string', Rule::in(self::VARANTY_VALUES)]
                : ['sometimes', 'string', Rule::in(self::VARANTY_VALUES)],
            'problem' => ['nullable', 'string'],
            'serviseproblem' => ['nullable', 'string'],
            'serviseproblemtechnik_id' => $forCreate
                ? ['required', 'integer', Rule::exists('service.members', 'id')]
                : ['sometimes', 'integer', Rule::exists('service.members', 'id')],
            'dopclient' => ['nullable', 'string'],
            'datepredavane' => $forCreate ? ['required', 'date'] : ['sometimes', 'date'],
            'saobshtilclient_id' => $forCreate
                ? ['required', 'integer', Rule::exists('service.members', 'id')]
                : ['sometimes', 'integer', Rule::exists('service.members', 'id')],
            'clientopisanie' => ['nullable', 'string', 'max:512'],
            'etap' => $forCreate
                ? ['required', 'string', Rule::in(self::ETAP_VALUES)]
                : ['sometimes', 'string', Rule::in(self::ETAP_VALUES)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedInput(Request $request): array
    {
        $input = $request->all();

        if (isset($input['schema_definition']) && is_array($input['schema_definition'])) {
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
