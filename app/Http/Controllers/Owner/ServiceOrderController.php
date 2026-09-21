<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\ServiceOrder;
use App\Services\Commercial;
use App\Support\CommercialPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A service order, as the venue reads and confirms it.
 */
class ServiceOrderController extends Controller
{
    public function __construct(private readonly Commercial $commercial) {}

    public function show(Request $request, ServiceOrder $order): Response
    {
        $this->assertOwn($request, $order);

        return Inertia::render('owner/service-order', [
            'order' => CommercialPresenter::order($order->load('event:id,title_ar,title_en')),
        ]);
    }

    public function accept(Request $request, ServiceOrder $order): RedirectResponse
    {
        $this->assertOwn($request, $order);

        $request->validate(['accept' => ['accepted']]);

        if (! $order->isSent()) {
            throw ValidationException::withMessages(['accept' => __('ui.orders.not_open')]);
        }

        $this->commercial->acceptOrder($order, $request->user(), $request);

        return to_route('owner.documents')->with('success', __('ui.orders.accepted_toast'));
    }

    private function assertOwn(Request $request, ServiceOrder $order): void
    {
        $place = $request->user()->places()->first();

        abort_if($place === null || $order->place_id !== $place->id, 404);
    }
}
