<?php

namespace App\Ai\Tools;

use App\Http\Resources\ServiceCardProductResource;
use App\Models\ServiceCardProduct;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ManageServiceCardProductsTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Управление на продадени продукти към сервизни карти (таблица ceni): list, show, create, update, delete.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return $this->encode(['error' => 'Няма логнат потребител.']);
        }

        $input = $request->all();
        if (isset($input['schema_definition']) && is_array($input['schema_definition'])) {
            $input = $input['schema_definition'];
        }

        $action = (string) ($input['action'] ?? '');

        return match ($action) {
            'list' => $this->listProducts($input),
            'show' => $this->showProduct($input),
            'create' => $this->createProduct($input),
            'update' => $this->updateProduct($input),
            'delete' => $this->deleteProduct($input),
            default => $this->encode(['error' => 'Невалидна стойност за action.']),
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->enum(['list', 'show', 'create', 'update', 'delete'])->required(),
            'id' => $schema->integer()->description('ID на ред от ceni.'),
            'project_id' => $schema->integer()->description('ID на сервизна карта (projects.id).'),
            'name' => $schema->string()->description('Име на продукт.'),
            'price' => $schema->number()->description('Крайна цена.'),
            'vat' => $schema->string()->description('Yes/No за ДДС.'),
            'broi' => $schema->integer()->description('Количество.'),
            'ed_cena' => $schema->number()->description('Единична цена.'),
        ];
    }

    /**
     * @param  array<string,mixed>  $input
     */
    private function listProducts(array $input): string
    {
        $v = Validator::make($input, [
            'project_id' => ['required', 'integer', Rule::exists('service.projects', 'id')],
        ]);
        if ($v->fails()) {
            return $this->encode(['error' => $v->errors()->first()]);
        }

        $rows = ServiceCardProduct::query()
            ->where('project_id', (int) $input['project_id'])
            ->orderBy('id')
            ->get();

        return $this->encode([
            'total' => $rows->count(),
            'data' => ServiceCardProductResource::collection($rows)->resolve(),
        ]);
    }

    /**
     * @param  array<string,mixed>  $input
     */
    private function showProduct(array $input): string
    {
        $v = Validator::make($input, [
            'id' => ['required', 'integer', Rule::exists('service.ceni', 'id')],
        ]);
        if ($v->fails()) {
            return $this->encode(['error' => $v->errors()->first()]);
        }

        $row = ServiceCardProduct::query()->findOrFail((int) $input['id']);

        return $this->encode((new ServiceCardProductResource($row))->resolve());
    }

    /**
     * @param  array<string,mixed>  $input
     */
    private function createProduct(array $input): string
    {
        $v = Validator::make($input, $this->fieldRules(true));
        if ($v->fails()) {
            return $this->encode(['error' => $v->errors()->first()]);
        }

        $row = ServiceCardProduct::query()->create($v->validated());

        return $this->encode((new ServiceCardProductResource($row))->resolve());
    }

    /**
     * @param  array<string,mixed>  $input
     */
    private function updateProduct(array $input): string
    {
        $v = Validator::make($input, array_merge([
            'id' => ['required', 'integer', Rule::exists('service.ceni', 'id')],
        ], $this->fieldRules(false)));
        if ($v->fails()) {
            return $this->encode(['error' => $v->errors()->first()]);
        }

        $validated = $v->validated();
        $row = ServiceCardProduct::query()->findOrFail((int) $validated['id']);
        unset($validated['id']);
        $row->update($validated);

        return $this->encode((new ServiceCardProductResource($row->fresh()))->resolve());
    }

    /**
     * @param  array<string,mixed>  $input
     */
    private function deleteProduct(array $input): string
    {
        $v = Validator::make($input, [
            'id' => ['required', 'integer', Rule::exists('service.ceni', 'id')],
        ]);
        if ($v->fails()) {
            return $this->encode(['error' => $v->errors()->first()]);
        }

        $id = (int) $input['id'];
        ServiceCardProduct::query()->findOrFail($id)->delete();

        return $this->encode(['ok' => true, 'id' => $id]);
    }

    /**
     * @return array<string,mixed>
     */
    private function fieldRules(bool $forCreate): array
    {
        return [
            'project_id' => $forCreate
                ? ['required', 'integer', Rule::exists('service.projects', 'id')]
                : ['sometimes', 'integer', Rule::exists('service.projects', 'id')],
            'name' => $forCreate ? ['required', 'string', 'max:256'] : ['sometimes', 'string', 'max:256'],
            'price' => $forCreate ? ['required', 'numeric', 'min:0'] : ['sometimes', 'numeric', 'min:0'],
            'vat' => $forCreate ? ['required', 'string', Rule::in(['Yes', 'No'])] : ['sometimes', 'string', Rule::in(['Yes', 'No'])],
            'broi' => $forCreate ? ['required', 'integer', 'min:1'] : ['sometimes', 'integer', 'min:1'],
            'ed_cena' => $forCreate ? ['required', 'numeric', 'min:0'] : ['sometimes', 'numeric', 'min:0'],
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function encode(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE)
            ?: '{"error":"JSON encode failed"}';
    }
}
