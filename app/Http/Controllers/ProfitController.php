<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProfitEntryRequest;
use App\Http\Requests\UpdateProfitEntryRequest;
use App\Models\ProfitEntry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfitController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', ProfitEntry::class);

        return Inertia::render('profits/Index');
    }

    public function store(StoreProfitEntryRequest $request): RedirectResponse
    {
        $this->authorize('create', ProfitEntry::class);

        /** @var User $user */
        $user = Auth::user();

        $user->profitEntries()->create($request->validated());

        return back();
    }

    public function update(UpdateProfitEntryRequest $request, ProfitEntry $profit): RedirectResponse
    {
        $this->authorize('update', $profit);

        $profit->update($request->validated());

        return back();
    }

    public function destroy(ProfitEntry $profit): RedirectResponse
    {
        $this->authorize('delete', $profit);

        $profit->delete();

        return back();
    }
}
