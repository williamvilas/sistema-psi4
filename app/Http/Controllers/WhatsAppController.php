<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\FeatureUsageEnum;
use App\Http\Requests\WhatsApp\SendRequest;
use App\Jobs\SaveFeatureUsageJob;
use App\Services\WhatsAppLinkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WhatsAppController extends Controller
{
    public function __construct(private readonly WhatsAppLinkService $links)
    {
    }

    public function contact(Request $request)
    {
        $message = $request->query('message');

        return redirect()->away($this->links->support(is_string($message) ? mb_substr($message, 0, 1000) : null));
    }

    public function send(SendRequest $request)
    {
        SaveFeatureUsageJob::dispatch(userId: Auth::id(), feature: FeatureUsageEnum::CLICK_WHATSAPP);

        return redirect()->away($this->links->toNumber($request->getPhone(), $request->getText()));
    }

    public function help()
    {
        SaveFeatureUsageJob::dispatch(userId: Auth::id(), feature: FeatureUsageEnum::HELP_WHATSAPP);

        return redirect()->away($this->links->support(__('messages.text_help', ['code' => Auth::user()->code])));
    }
}
