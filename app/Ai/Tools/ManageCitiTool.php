<?php

namespace App\Ai\Tools;

use App\Models\Citi;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ManageCitiTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'CRUD за населени места (citi): list, count, show, create, update, delete.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return $this->encode(['error' => 'Няма логнат потребител.']);
        }

        $action = $request->string('action')->toString();

        return match ($action) {
            'list' => $this->list($request),
            'count' => $this->encode(['total' => Citi::query()->count()]),
            'show' => $this->show($request),
            'create' => $this->create($request),
            'update' => $this->update($request),
            'delete' => $this->delete($request),
            default => $this->encode(['error' => 'Невалидна стойност за action.']),
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->enum(['list', 'count', 'show', 'create', 'update', 'delete'])->required(),
            'id' => $schema->integer()->description('ID на населено място.'),
            'name' => $schema->string()->description('Име на населено място.'),
            'postalcod' => $schema->string()->description('Пощенски код (до 4).'),
            'q' => $schema->string()->description('Търсене при list.'),
            'limit' => $schema->integer()->description('Лимит за list (1-200).'),
        ];
    }

    private function list(Request $request): string
    {
        $input = $request->all();
        $limit = max(1, min((int) ($input['limit'] ?? 100), 200));
        $query = Citi::query()->orderBy('name');
        if (! empty($input['q']) && is_string($input['q'])) {
            $query->where('name', 'like', '%'.trim($input['q']).'%');
        }

        $rows = $query->limit($limit)->get(['id', 'name', 'postalcod']);

        return $this->encode([
            'total' => (clone $query)->count(),
            'returned' => $rows->count(),
            'data' => $rows,
        ]);
    }

    private function show(Request $request): string
    {
        $v = Validator::make($request->all(), ['id' => ['required', 'integer', 'exists:service.citi,id']]);
        if ($v->fails()) {
            return $this->encode(['error' => $v->errors()->first()]);
        }

        return $this->encode([
            'data' => Citi::query()->findOrFail($request->integer('id'))->only(['id', 'name', 'postalcod']),
        ]);
    }

    private function create(Request $request): string
    {
        $v = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:45'],
            'postalcod' => ['nullable', 'string', 'max:4'],
        ]);
        if ($v->fails()) {
            return $this->encode(['error' => $v->errors()->first()]);
        }

        $row = Citi::query()->create($v->validated());

        return $this->encode(['data' => $row->only(['id', 'name', 'postalcod'])]);
    }

    private function update(Request $request): string
    {
        $v = Validator::make($request->all(), [
            'id' => ['required', 'integer', 'exists:service.citi,id'],
            'name' => ['sometimes', 'required', 'string', 'max:45'],
            'postalcod' => ['sometimes', 'nullable', 'string', 'max:4'],
        ]);
        if ($v->fails()) {
            return $this->encode(['error' => $v->errors()->first()]);
        }

        $row = Citi::query()->findOrFail($request->integer('id'));
        $row->update(collect($v->validated())->except('id')->all());

        return $this->encode(['data' => $row->fresh()->only(['id', 'name', 'postalcod'])]);
    }

    private function delete(Request $request): string
    {
        $v = Validator::make($request->all(), ['id' => ['required', 'integer', 'exists:service.citi,id']]);
        if ($v->fails()) {
            return $this->encode(['error' => $v->errors()->first()]);
        }

        $id = $request->integer('id');
        Citi::query()->findOrFail($id)->delete();

        return $this->encode(['ok' => true, 'id' => $id]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encode(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{"error":"JSON encode failed"}';
    }
}
