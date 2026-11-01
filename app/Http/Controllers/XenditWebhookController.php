<?php

namespace App\Http\Controllers;

use App\Actions\ProcessXenditWebhook;
use App\Http\Requests\XenditWebhookRequest;
use Illuminate\Http\Response;

class XenditWebhookController extends Controller
{
    public function __invoke(XenditWebhookRequest $request, ProcessXenditWebhook $process): Response
    {
        $process->handle($request->validated());

        return response()->noContent();
    }
}
