<?php

namespace App\Http\Controllers;

use App\Actions\ImportServiceRecords;
use App\Support\CsvExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminRecordImportController extends Controller
{
    public function create(): StreamedResponse
    {
        Gate::authorize('records.manage');

        return CsvExport::download('historical-record-template.csv', ImportServiceRecords::COLUMNS, []);
    }

    public function store(Request $request, ImportServiceRecords $import): RedirectResponse
    {
        Gate::authorize('records.manage');
        $data = $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'], 'reason' => ['required', 'string', 'max:1000']]);
        $count = $import->handle($data['file'], $data['reason']);

        return back()->with('status', "Imported {$count} preserved historical records.");
    }
}
