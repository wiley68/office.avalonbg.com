<?php

namespace App\Ai\Tools;

use App\Http\Resources\ContactResource;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ManageContactsTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Управление на контакти от service базата: list, show, create, update, delete.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return json_encode(['error' => 'Няма логнат потребител.'], JSON_UNESCAPED_UNICODE);
        }

        $action = $request->string('action')->toString();

        return match ($action) {
            'list' => $this->listContacts(),
            'show' => $this->showContact($request),
            'create' => $this->createContact($request),
            'update' => $this->updateContact($request),
            'delete' => $this->deleteContact($request),
            default => json_encode(['error' => 'Невалидна стойност за action.'], JSON_UNESCAPED_UNICODE),
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->enum(['list', 'show', 'create', 'update', 'delete'])
                ->description('Операция с контакти.')
                ->required(),
            'id' => $schema->integer()->description('ID на контакт.'),
            'q' => $schema->string()->description('Търсене при list.'),
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

    private function listContacts(): string
    {
        $rows = Contact::query()->with(['citi', 'dlazhnost'])->latest('id')->limit(50)->get();

        return json_encode(
            ContactResource::collection($rows)->resolve(),
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );
    }

    private function showContact(Request $request): string
    {
        $v = Validator::make($request->all(), [
            'id' => ['required', 'integer', 'exists:service.contacts,id'],
        ]);

        if ($v->fails()) {
            return json_encode(['error' => $v->errors()->first()], JSON_UNESCAPED_UNICODE);
        }

        $row = Contact::query()->with(['citi', 'dlazhnost'])->findOrFail($request->integer('id'));

        return json_encode((new ContactResource($row))->resolve(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function createContact(Request $request): string
    {
        $v = Validator::make($request->all(), [
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
            return json_encode(['error' => $v->errors()->first()], JSON_UNESCAPED_UNICODE);
        }

        $row = Contact::query()->create($v->validated());

        return json_encode((new ContactResource($row->fresh()->load(['citi', 'dlazhnost'])))->resolve(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function updateContact(Request $request): string
    {
        $v = Validator::make($request->all(), [
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
            return json_encode(['error' => $v->errors()->first()], JSON_UNESCAPED_UNICODE);
        }

        $row = Contact::query()->findOrFail($request->integer('id'));
        $row->update(collect($v->validated())->except('id')->all());

        return json_encode((new ContactResource($row->fresh()->load(['citi', 'dlazhnost'])))->resolve(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function deleteContact(Request $request): string
    {
        $v = Validator::make($request->all(), [
            'id' => ['required', 'integer', 'exists:service.contacts,id'],
        ]);

        if ($v->fails()) {
            return json_encode(['error' => $v->errors()->first()], JSON_UNESCAPED_UNICODE);
        }

        $id = $request->integer('id');
        Contact::query()->findOrFail($id)->delete();

        return json_encode(['ok' => true, 'id' => $id], JSON_UNESCAPED_UNICODE);
    }
}
