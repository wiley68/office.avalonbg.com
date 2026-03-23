<?php

namespace App\Ai\Tools;

use App\Http\Resources\NoteResource;
use App\Models\Note;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Един tool за CRUD на бележки на текущия потребител.
 * Алтернатива са отделни tools (напр. list_notes, create_note) — по-ясни за модела, но повече boilerplate.
 */
class ManageNotesTool implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Управление на лични бележки (notes) на логнатия потребител: list, count, show, create, update, delete. '
            .'Изисква автентикиран потребител в текущата HTTP сесия.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return json_encode(['error' => 'Няма логнат потребител.'], JSON_UNESCAPED_UNICODE);
        }

        $action = $request->string('action')->toString();

        return match ($action) {
            'list' => $this->listNotes($user, $request),
            'count' => $this->countNotes($user),
            'show' => $this->showNote($request),
            'create' => $this->createNote($user, $request),
            'update' => $this->updateNote($request),
            'delete' => $this->deleteNote($request),
            default => json_encode(['error' => 'Невалидна стойност за action.'], JSON_UNESCAPED_UNICODE),
        };
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->enum(['list', 'count', 'show', 'create', 'update', 'delete'])
                ->description('Операция: list — всички бележки; show/create/update/delete — според id и полета.')
                ->required(),
            'q' => $schema
                ->string()
                ->description('Търсене при list.'),
            'limit' => $schema
                ->integer()
                ->description('Брой редове за list (по подразбиране 50, максимум 200).'),
            'page' => $schema
                ->integer()
                ->description('Номер на страница за list (по подразбиране 1).'),
            'per_page' => $schema
                ->integer()
                ->description('Редове на страница за list (1-200, по подразбиране 50).'),
            'offset' => $schema
                ->integer()
                ->description('Offset за list (ако е зададен, има приоритет над page).'),
            'id' => $schema
                ->integer()
                ->description('ID на бележка (за show, update, delete).'),
            'name' => $schema
                ->string()
                ->description('Заглавие до 40 символа (create/update).'),
            'description' => $schema
                ->string()
                ->description('Кратко описание до 120 символа, по избор (create/update).'),
            'note' => $schema
                ->string()
                ->description('Пълно съдържание на бележката (create/update).'),
        ];
    }

    private function listNotes(User $user, Request $request): string
    {
        $input = $request->all();
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

        $query = $user->notes()->getQuery()->latest('id');

        if (! empty($input['q']) && is_string($input['q'])) {
            $search = trim($input['q']);
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%");
            });
        }

        $total = (clone $query)->count();
        $notes = $query->offset($offset)->limit($perPage)->get();
        $currentPage = intdiv($offset, $perPage) + 1;
        $lastPage = max(1, (int) ceil($total / $perPage));

        return json_encode(
            [
                'total' => $total,
                'returned' => $notes->count(),
                'page' => $currentPage,
                'per_page' => $perPage,
                'last_page' => $lastPage,
                'offset' => $offset,
                'data' => NoteResource::collection($notes)->resolve(),
            ],
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );
    }

    private function countNotes(User $user): string
    {
        return json_encode([
            'total' => $user->notes()->count(),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function showNote(Request $request): string
    {
        $v = Validator::make($request->all(), [
            'id' => ['required', 'integer', 'exists:notes,id'],
        ]);

        if ($v->fails()) {
            return json_encode(['error' => $v->errors()->first()], JSON_UNESCAPED_UNICODE);
        }

        $note = Note::query()->findOrFail($request->integer('id'));
        if (Gate::denies('view', $note)) {
            return json_encode(['error' => 'Нямате достъп до тази бележка.'], JSON_UNESCAPED_UNICODE);
        }

        return json_encode((new NoteResource($note))->resolve(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function createNote(User $user, Request $request): string
    {
        if (Gate::denies('create', Note::class)) {
            return json_encode(['error' => 'Нямате право да създавате бележки.'], JSON_UNESCAPED_UNICODE);
        }

        $v = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:120'],
            'note' => ['required', 'string'],
        ]);

        if ($v->fails()) {
            return json_encode(['error' => $v->errors()->first()], JSON_UNESCAPED_UNICODE);
        }

        $note = $user->notes()->create($v->validated());

        return json_encode((new NoteResource($note))->resolve(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function updateNote(Request $request): string
    {
        $v = Validator::make($request->all(), [
            'id' => ['required', 'integer', 'exists:notes,id'],
            'name' => ['sometimes', 'required', 'string', 'max:40'],
            'description' => ['sometimes', 'nullable', 'string', 'max:120'],
            'note' => ['sometimes', 'required', 'string'],
        ]);

        if ($v->fails()) {
            return json_encode(['error' => $v->errors()->first()], JSON_UNESCAPED_UNICODE);
        }

        $note = Note::query()->findOrFail($request->integer('id'));
        if (Gate::denies('update', $note)) {
            return json_encode(['error' => 'Нямате достъп до тази бележка.'], JSON_UNESCAPED_UNICODE);
        }

        $payload = collect($v->validated())->except('id')->all();
        $note->update($payload);

        return json_encode((new NoteResource($note->fresh()))->resolve(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function deleteNote(Request $request): string
    {
        $v = Validator::make($request->all(), [
            'id' => ['required', 'integer', 'exists:notes,id'],
        ]);

        if ($v->fails()) {
            return json_encode(['error' => $v->errors()->first()], JSON_UNESCAPED_UNICODE);
        }

        $note = Note::query()->findOrFail($request->integer('id'));
        if (Gate::denies('delete', $note)) {
            return json_encode(['error' => 'Нямате достъп до тази бележка.'], JSON_UNESCAPED_UNICODE);
        }

        $id = $request->integer('id');
        $note->delete();

        return json_encode(['ok' => true, 'id' => $id], JSON_UNESCAPED_UNICODE);
    }
}
