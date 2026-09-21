<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\AgreementAcceptance;
use App\Models\AgreementVersion;
use App\Models\CommercialOffer;
use App\Models\ServiceOrder;
use App\Services\Agreements;
use App\Services\Commercial;
use App\Support\CommercialPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Agreements and documents: everything this venue has signed, in one place.
 *
 * Four sections -- the Partner Terms, the commercial offer, services and
 * orders, and the history of it all. Nothing here can be changed; it is a
 * record, with a way to each thing that still needs a decision.
 */
class DocumentsController extends Controller
{
    public function __construct(
        private readonly Agreements $agreements,
        private readonly Commercial $commercial,
    ) {}

    public function __invoke(Request $request): Response
    {
        $place = $request->user()->places()->first();

        // A super admin, or an owner not yet linked to a venue, has no
        // documents. An explanatory empty state, like every other owner
        // page, rather than a 403 for clicking a link in their own sidebar.
        if ($place === null) {
            return Inertia::render('owner/documents', ['place' => null]);
        }

        $current = $this->agreements->current();
        $termsAcceptance = $current === null ? null
            : $place->acceptances()->where('agreement_version_id', $current->id)->first();

        $offers = $place->offers()->with('acceptor:id,name')->latest('id')->get();
        $orders = $place->serviceOrders()->with('event:id,title_ar,title_en')->latest('id')->get();

        return Inertia::render('owner/documents', [
            'place' => ['name_ar' => $place->name_ar, 'name_en' => $place->name_en],
            'terms' => [
                'current' => $current === null ? null : [
                    'version' => $current->version,
                    'title_ar' => $current->title_ar,
                    'title_en' => $current->title_en,
                    'published_at' => $current->published_at?->toIso8601String(),
                ],
                'accepted' => $termsAcceptance === null ? null : [
                    'accepted_at' => $termsAcceptance->accepted_at->toIso8601String(),
                    'representative_name' => $termsAcceptance->representative_name,
                    'representative_role' => $termsAcceptance->representative_role,
                ],
                'needs_acceptance' => $this->agreements->needsAcceptance($request->user()),
            ],
            'offer' => [
                'current' => ($offer = $this->commercial->currentOffer($place)) === null ? null : CommercialPresenter::offer($offer),
                'pending' => $offers
                    ->filter(fn (CommercialOffer $offer) => $offer->isSent() && ! $offer->isExpired())
                    ->map(fn (CommercialOffer $offer) => CommercialPresenter::offer($offer))
                    ->values()->all(),
            ],
            'orders' => $orders
                ->reject(fn (ServiceOrder $order) => $order->isDraft())
                ->map(fn (ServiceOrder $order) => CommercialPresenter::order($order))
                ->values()->all(),
            'history' => $this->history($place->acceptances()->with('version')->get(), $offers, $orders),
        ]);
    }

    /**
     * Every acceptance this venue has made, newest first.
     *
     * @param  Collection<int, AgreementAcceptance>  $acceptances
     * @param  Collection<int, CommercialOffer>  $offers
     * @param  Collection<int, ServiceOrder>  $orders
     * @return array<int, array<string, mixed>>
     */
    private function history($acceptances, $offers, $orders): array
    {
        $rows = collect();

        foreach ($acceptances as $acceptance) {
            /** @var AgreementVersion $version */
            $version = $acceptance->version;
            $rows->push([
                'kind' => 'terms',
                'title_ar' => $version->title_ar,
                'title_en' => $version->title_en,
                'reference' => $version->version,
                'at' => $acceptance->accepted_at->toIso8601String(),
                'representative' => $acceptance->representative_name,
                'method' => $acceptance->acceptance_method,
                'hash' => $acceptance->content_hash,
                'href' => '/owner/agreement',
            ]);
        }

        foreach ($offers->whereNotNull('accepted_at') as $offer) {
            $rows->push([
                'kind' => 'offer',
                'title_ar' => $offer->title_ar,
                'title_en' => $offer->title_en,
                'reference' => '#'.$offer->id,
                'at' => $offer->accepted_at?->toIso8601String(),
                'representative' => $offer->representative_name,
                'method' => $offer->acceptance_method,
                'hash' => $offer->content_hash,
                'href' => '/owner/commercial-offers/'.$offer->id,
            ]);
        }

        foreach ($orders->whereNotNull('accepted_at') as $order) {
            $rows->push([
                'kind' => 'order',
                'title_ar' => $order->title_ar,
                'title_en' => $order->title_en,
                'reference' => '#'.$order->id,
                'at' => $order->accepted_at?->toIso8601String(),
                'representative' => $order->representative_name,
                'method' => $order->acceptance_method,
                'hash' => $order->content_hash,
                'href' => '/owner/service-orders/'.$order->id,
            ]);
        }

        return $rows->sortByDesc('at')->values()->all();
    }
}
