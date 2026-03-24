<?php

namespace App\Ai\Tools;

use App\Models\Dlazhnost;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ManageDlazhnostiTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'CRUD за длъжности (dlaznosti): list, count, show, create, update, delete.';
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
            'count' => $this->encode(['total' => Dlazhnost::query()->count()]),
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
            'id' => $schema->integer()->description('ID на длъжност.'),
            'name' => $schema->string()->description('Име на длъжност.'),
            'q' => $schema->string()->description('Търсене при list.'),
            'limit' => $schema->integer()->description('Лимит за list (1-200).'),
        ];
    }

    private function list(Request $request): string
    {
        $input = $request->all();
        $limit = max(1, min((int) ($input['limit'] ?? 100), 200));
        $query = Dlazhnost::query()->orderBy('name');
        if (! empty($input['q']) && is_string($input['q'])) {
            $query->where('name', 'like', '%'.trim($input['q']).'%');
        }

        $rows = $query->limit($limit)->get(['id', 'name']);

        return $this->encode([
            'total' => (clone $query)->count(),
            'returned' => $rows->count(),
            'data' => $rows,
        ]);
    }

    private function show(Request $request): string
    {
        $v = Validator::make($request->all(), ['id' => ['required', 'integer', 'exists:service.dlaznosti,id']]);
        if ($v->fails()) {
            return $this->encode(['error' => $v->errors()->first()]);
        }

        return $this->encode([
            'data' => Dlazhnost::query()->findOrFail($request->integer('id'))->only(['id', 'name']),
        ]);
    }

    private function create(Request $request): string
    {
        $v = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:45'],
        ]);
        if ($v->fails()) {
            return $this->encode(['error' => $v->errors()->first()]);
        }

        $row = Dlazhnost::query()->create($v->validated());

        return $this->encode(['data' => $row->only(['id', 'name'])]);
    }

    private function update(Request $request): string
    {
        $v = Validator::make($request->all(), [
            'id' => ['required', 'integer', 'exists:service.dlaznosti,id'],
            'name' => ['sometimes', 'required', 'string', 'max:45'],
        ]);
        if ($v->fails()) {
            return $this->encode(['error' => $v->errors()->first()]);
        }

        $row = Dlazhnost::query()->findOrFail($request->integer('id'));
        $row->update(collect($v->validated())->except('id')->all());

        return $this->encode(['data' => $row->fresh()->only(['id', 'name'])]);
    }

    private function delete(Request $request): string
    {
        $v = Validator::make($request->all(), ['id' => ['required', 'integer', 'exists:service.dlaznosti,id']]);
        if ($v->fails()) {
            return $this->encode(['error' => $v->errors()->first()]);
        }

        $id = $request->integer('id');
        Dlazhnost::query()->findOrFail($id)->delete();

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
