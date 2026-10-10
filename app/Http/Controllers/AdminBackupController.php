<?php

namespace App\Http\Controllers;

use App\Actions\CreateOfficeBackup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminBackupController extends Controller
{
    public function store(Request $request, CreateOfficeBackup $backup): StreamedResponse
    {
        Gate::authorize('manage-admin-roles');
        $request->validate(['password' => ['required', 'current_password:web']]);
        $path = $backup->handle();

        return Storage::disk('local')->download($path, basename($path), ['Cache-Control' => 'private, no-store']);
    }
}
