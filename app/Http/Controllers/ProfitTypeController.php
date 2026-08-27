<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProfitTypeRequest;
use App\Models\ProfitType;
use Illuminate\Http\RedirectResponse;

class ProfitTypeController extends Controller
{
    public function store(StoreProfitTypeRequest $request): RedirectResponse
    {
        $this->authorize('create', ProfitType::class);

        ProfitType::query()->create($request->validated());

        return back();
    }
}
