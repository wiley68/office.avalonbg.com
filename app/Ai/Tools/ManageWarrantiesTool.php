<?php

namespace App\Ai\Tools;

use App\Http\Resources\WarrantyResource;
use App\Models\User;
use App\Models\Warranty;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class ManageWarrantiesTool implements Tool
{
    /** @var list<string> */
    private const SERVICE_VALUES = ['в сервиз', 'при клиента'];

    /** @var list<string> */
    private const OBSLUZVANE_VALUES = ['4-8', '8-16', '8-32'];

    /** @var list<string> */
    private const ISCOMP_VALUES = ['Yes', 'No'];

    public function description(): Stringable|string
    {
        return 'Управление на гаранционни карти (таблица varanty, service DB): list, count, show, create, update, delete. '
            .'Връзка client_id към contacts. При iscomp=Yes се пълнят полетата за компоненти на компютър и серийните им номера.';
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
                'list' => $this->listWarranties($input),
                'count' => $this->countWarranties(),
                'show' => $this->showWarranty($input),
                'create' => $this->createWarranty($input),
                'update' => $this->updateWarranty($input),
                'delete' => $this->deleteWarranty($input),
                default => $this->encode(['error' => 'Невалидна стойност за action.']),
            };
        } catch (Throwable $e) {
            return $this->encode([
                'error' => 'Грешка при обработка на warranties tool.',
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
                ->description('Операция с гаранционни карти.')
                ->required(),
            'id' => $schema->integer()->description('ID на запис в varanty.'),
            'client_id' => $schema->integer()->description('ID на контакт (contacts).'),
            'q' => $schema->string()->description('Търсене при list (продукт, сериен, име на контакт).'),
            'limit' => $schema->integer()->description('Брой редове за list (по подразбиране 50, максимум 200).'),
            'page' => $schema->integer()->description('Номер на страница за list.'),
            'per_page' => $schema->integer()->description('Редове на страница за list (1–200).'),
            'offset' => $schema->integer()->description('Offset за list (ако е зададен, има приоритет над page).'),
            'date_sell' => $schema->string()->description('Дата на продажба / издаване (ISO или Y-m-d).'),
            'service' => $schema->string()->description('Тип обслужване: „в сервиз“ или „при клиента“.'),
            'obsluzvane' => $schema->string()->description('Време за реакция: 4-8, 8-16 или 8-32.'),
            'product' => $schema->string()->description('Наименование на продукта.'),
            'sernum' => $schema->string()->description('Сериен номер на картата.'),
            'invoice' => $schema->string()->description('Номер на фактура.'),
            'varanty_period' => $schema->string()->description('Текстово описание на гаранционния период.'),
            'note' => $schema->string()->description('Бележка.'),
            'iscomp' => $schema->string()->description('Дали е компютър: Yes или No.'),
            'motherboard' => $schema->string()->description('Дънна платка (име).'),
            'processor' => $schema->string()->description('Процесор.'),
            'ram' => $schema->string()->description('RAM.'),
            'psu' => $schema->string()->description('Захранване.'),
            'hdd1' => $schema->string()->description('HDD1.'),
            'hdd2' => $schema->string()->description('HDD2.'),
            'dvd' => $schema->string()->description('Оптика.'),
            'vga' => $schema->string()->description('Видео.'),
            'lan' => $schema->string()->description('Мрежа.'),
            'speackers' => $schema->string()->description('Тонколони.'),
            'printer' => $schema->string()->description('Принтер.'),
            'monitor' => $schema->string()->description('Монитор.'),
            'kbd' => $schema->string()->description('Клавиатура.'),
            'mouse' => $schema->string()->description('Мишка.'),
            'other' => $schema->string()->description('Друго.'),
            'motherboardsn' => $schema->string()->description('Сериен № дъно.'),
            'processorsn' => $schema->string()->description('Сериен № процесор.'),
            'ramsn' => $schema->string()->description('Сериен № RAM.'),
            'psusn' => $schema->string()->description('Сериен № PSU.'),
            'hdd1sn' => $schema->string()->description('Сериен № HDD1.'),
            'hdd2sn' => $schema->string()->description('Сериен № HDD2.'),
            'dvdsn' => $schema->string()->description('Сериен № DVD.'),
            'vgasn' => $schema->string()->description('Сериен № VGA.'),
            'lansn' => $schema->string()->description('Сериен № LAN.'),
            'speackerssn' => $schema->string()->description('Сериен № тонколони.'),
            'printersn' => $schema->string()->description('Сериен № принтер.'),
            'monitorsn' => $schema->string()->description('Сериен № монитор.'),
            'kbdsn' => $schema->string()->description('Сериен № клавиатура.'),
            'mousesn' => $schema->string()->description('Сериен № мишка.'),
            'othersn' => $schema->string()->description('Сериен № друго.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function listWarranties(array $input): string
    {
        $perPage = max(
            1,
            min(
                (int) ($input['per_page'] ?? $input['limit'] ?? 50),
                200
            ),
        );
        $page = max(1, (int) ($input['page'] ?? 1));
        $offset = array_key_exists('offset', $input)
            ? max(0, (int) $input['offset'])
            : (($page - 1) * $perPage);

        $query = Warranty::query()->with('contact')->latest('id');

        if (! empty($input['q']) && is_string($input['q'])) {
            $search = trim($input['q']);
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('product', 'like', "%{$search}%")
                    ->orWhere('sernum', 'like', "%{$search}%")
                    ->orWhereHas('contact', function ($q) use ($search): void {
                        $q
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('second_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('firm', 'like', "%{$search}%");
                    });
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
                'data' => WarrantyResource::collection($rows)->resolve(),
            ],
        );
    }

    private function countWarranties(): string
    {
        return $this->encode([
            'total' => Warranty::query()->count(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function showWarranty(array $input): string
    {
        $v = Validator::make($input, [
            'id' => ['required', 'integer', Rule::exists('service.varanty', 'id')],
        ]);

        if ($v->fails()) {
            return $this->encode(['error' => $v->errors()->first()]);
        }

        $row = Warranty::query()->with('contact')->findOrFail((int) $input['id']);

        return $this->encode((new WarrantyResource($row))->resolve());
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function createWarranty(array $input): string
    {
        $v = Validator::make($input, $this->warrantyFieldRules(forCreate: true));

        if ($v->fails()) {
            return $this->encode(['error' => $v->errors()->first()]);
        }

        $row = Warranty::query()->create($v->validated());

        return $this->encode((new WarrantyResource($row->fresh()->load('contact')))->resolve());
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function updateWarranty(array $input): string
    {
        $v = Validator::make($input, array_merge(
            [
                'id' => ['required', 'integer', Rule::exists('service.varanty', 'id')],
            ],
            $this->warrantyFieldRules(forCreate: false),
        ));

        if ($v->fails()) {
            return $this->encode(['error' => $v->errors()->first()]);
        }

        $validated = $v->validated();
        $id = (int) $validated['id'];
        unset($validated['id']);

        $row = Warranty::query()->findOrFail($id);
        $row->update($validated);

        return $this->encode((new WarrantyResource($row->fresh()->load('contact')))->resolve());
    }

    /**
     * @return array<string, mixed>
     */
    private function warrantyFieldRules(bool $forCreate): array
    {
        $req = $forCreate ? 'required' : 'sometimes';

        return [
            'client_id' => $forCreate
                ? ['required', 'integer', Rule::exists('service.contacts', 'id')]
                : ['sometimes', 'integer', Rule::exists('service.contacts', 'id')],
            'date_sell' => [$req, 'date'],
            'service' => [$req, 'string', Rule::in(self::SERVICE_VALUES)],
            'obsluzvane' => [$req, 'string', Rule::in(self::OBSLUZVANE_VALUES)],
            'product' => ['nullable', 'string', 'max:256'],
            'sernum' => ['nullable', 'string', 'max:128'],
            'invoice' => ['nullable', 'string', 'max:45'],
            'varanty_period' => ['nullable', 'string', 'max:128'],
            'note' => ['nullable', 'string'],
            'motherboard' => ['nullable', 'string', 'max:128'],
            'processor' => ['nullable', 'string', 'max:128'],
            'ram' => ['nullable', 'string', 'max:128'],
            'psu' => ['nullable', 'string', 'max:128'],
            'hdd1' => ['nullable', 'string', 'max:128'],
            'hdd2' => ['nullable', 'string', 'max:128'],
            'dvd' => ['nullable', 'string', 'max:128'],
            'vga' => ['nullable', 'string', 'max:128'],
            'lan' => ['nullable', 'string', 'max:128'],
            'speackers' => ['nullable', 'string', 'max:128'],
            'printer' => ['nullable', 'string', 'max:128'],
            'monitor' => ['nullable', 'string', 'max:128'],
            'kbd' => ['nullable', 'string', 'max:128'],
            'mouse' => ['nullable', 'string', 'max:128'],
            'other' => ['nullable', 'string', 'max:128'],
            'iscomp' => ['nullable', 'string', Rule::in(self::ISCOMP_VALUES)],
            'motherboardsn' => ['nullable', 'string', 'max:45'],
            'processorsn' => ['nullable', 'string', 'max:45'],
            'ramsn' => ['nullable', 'string', 'max:45'],
            'psusn' => ['nullable', 'string', 'max:45'],
            'hdd1sn' => ['nullable', 'string', 'max:45'],
            'hdd2sn' => ['nullable', 'string', 'max:45'],
            'dvdsn' => ['nullable', 'string', 'max:45'],
            'vgasn' => ['nullable', 'string', 'max:45'],
            'lansn' => ['nullable', 'string', 'max:45'],
            'speackerssn' => ['nullable', 'string', 'max:45'],
            'printersn' => ['nullable', 'string', 'max:45'],
            'monitorsn' => ['nullable', 'string', 'max:45'],
            'kbdsn' => ['nullable', 'string', 'max:45'],
            'mousesn' => ['nullable', 'string', 'max:45'],
            'othersn' => ['nullable', 'string', 'max:45'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function deleteWarranty(array $input): string
    {
        $v = Validator::make($input, [
            'id' => ['required', 'integer', Rule::exists('service.varanty', 'id')],
        ]);

        if ($v->fails()) {
            return $this->encode(['error' => $v->errors()->first()]);
        }

        $id = (int) $input['id'];
        Warranty::query()->findOrFail($id)->delete();

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
