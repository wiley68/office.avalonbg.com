<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfitExportRequest;
use App\Http\Requests\StoreProfitEntryRequest;
use App\Http\Requests\UpdateProfitEntryRequest;
use App\Models\ProfitEntry;
use App\Models\User;
use App\Services\ProfitsPdfExportService;
use App\Services\ProfitsXlsxExportService;
use App\Support\ProfitExportFilename;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProfitController extends Controller
{
    public function index(): InertiaResponse
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

    public function export(
        ProfitExportRequest $request,
        ProfitsXlsxExportService $xlsxExportService,
        ProfitsPdfExportService $pdfExportService,
    ): BinaryFileResponse|Response {
        $this->authorize('viewAny', ProfitEntry::class);

        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validated();
        $dateFrom = $validated['date_from'];
        $dateTo = $validated['date_to'];
        $format = $validated['format'];

        $filename = ProfitExportFilename::make($dateFrom, $dateTo, $format);

        if ($format === 'pdf') {
            return $pdfExportService->download($user, $dateFrom, $dateTo, $filename);
        }

        $directory = storage_path('app/temp');
        File::ensureDirectoryExists($directory);

        $absolutePath = $directory.DIRECTORY_SEPARATOR.$filename;
        $xlsxExportService->writeToFile($user, $dateFrom, $dateTo, $absolutePath);

        return response()
            ->download($absolutePath, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }
}
