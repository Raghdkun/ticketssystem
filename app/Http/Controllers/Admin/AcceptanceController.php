<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgreementAcceptance;
use App\Models\CommercialOffer;
use App\Models\ServiceOrder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every acceptance on the platform, in one log.
 *
 * Terms, offers and orders are three tables with three shapes; here they
 * are one list an administrator can narrow by venue, kind and date. Read
 * only, by construction: nothing on this screen can change a record.
 */
class AcceptanceController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $q = $request->string('q')->trim()->value();
        $kind = $request->string('kind')->value();
        $from = $this->date($request->string('from')->value());
        $to = $this->date($request->string('to')->value())?->endOfDay();

        $rows = collect();

        if ($kind === '' || $kind === 'terms') {
            AgreementAcceptance::query()
                ->with(['version:id,version,title_ar,title_en', 'place:id,slug,name_ar,name_en', 'user:id,name'])
                ->when($q !== '', fn ($query) => $query->whereHas('place', fn ($p) => $this->venueMatches($p, $q)))
                ->when($from, fn ($query) => $query->where('accepted_at', '>=', $from))
                ->when($to, fn ($query) => $query->where('accepted_at', '<=', $to))
                ->latest('accepted_at')
                ->limit(200)
                ->get()
                ->each(fn (AgreementAcceptance $a) => $rows->push([
                    'kind' => 'terms',
                    'id' => $a->id,
                    'title_ar' => $a->version->title_ar,
                    'title_en' => $a->version->title_en,
                    'reference' => $a->version->version,
                    'place_ar' => $a->place->name_ar,
                    'place_en' => $a->place->name_en,
                    'place_slug' => $a->place->slug,
                    'representative' => $a->representative_name,
                    'role' => $a->representative_role,
                    'phone' => $a->representative_phone,
                    'by' => $a->user?->name,
                    'method' => $a->acceptance_method,
                    'otp' => $a->otp_channel,
                    'ip' => $a->ip,
                    'hash' => $a->content_hash,
                    'at' => $a->accepted_at->toIso8601String(),
                    'href' => '/admin/agreements/'.$a->agreement_version_id,
                ]));
        }

        if ($kind === '' || $kind === 'offer') {
            CommercialOffer::query()
                ->whereNotNull('accepted_at')
                ->with(['place:id,slug,name_ar,name_en', 'acceptor:id,name'])
                ->when($q !== '', fn ($query) => $query->whereHas('place', fn ($p) => $this->venueMatches($p, $q)))
                ->when($from, fn ($query) => $query->where('accepted_at', '>=', $from))
                ->when($to, fn ($query) => $query->where('accepted_at', '<=', $to))
                ->latest('accepted_at')
                ->limit(200)
                ->get()
                ->each(fn (CommercialOffer $o) => $rows->push([
                    'kind' => 'offer',
                    'id' => $o->id,
                    'title_ar' => $o->title_ar,
                    'title_en' => $o->title_en,
                    'reference' => '#'.$o->id,
                    'place_ar' => $o->place->name_ar,
                    'place_en' => $o->place->name_en,
                    'place_slug' => $o->place->slug,
                    'representative' => $o->representative_name,
                    'role' => $o->representative_role,
                    'phone' => $o->representative_phone,
                    'by' => $o->acceptor?->name,
                    'method' => $o->acceptance_method,
                    'otp' => 'none',
                    'ip' => $o->ip,
                    'hash' => $o->content_hash,
                    'at' => $o->accepted_at?->toIso8601String(),
                    'href' => '/admin/commercial-offers/'.$o->id,
                ]));
        }

        if ($kind === '' || $kind === 'order') {
            ServiceOrder::query()
                ->whereNotNull('accepted_at')
                ->with(['place:id,slug,name_ar,name_en'])
                ->when($q !== '', fn ($query) => $query->whereHas('place', fn ($p) => $this->venueMatches($p, $q)))
                ->when($from, fn ($query) => $query->where('accepted_at', '>=', $from))
                ->when($to, fn ($query) => $query->where('accepted_at', '<=', $to))
                ->latest('accepted_at')
                ->limit(200)
                ->get()
                ->each(fn (ServiceOrder $s) => $rows->push([
                    'kind' => 'order',
                    'id' => $s->id,
                    'title_ar' => $s->title_ar,
                    'title_en' => $s->title_en,
                    'reference' => '#'.$s->id,
                    'place_ar' => $s->place->name_ar,
                    'place_en' => $s->place->name_en,
                    'place_slug' => $s->place->slug,
                    'representative' => $s->representative_name,
                    'role' => $s->representative_role,
                    'phone' => $s->representative_phone,
                    'by' => null,
                    'method' => $s->acceptance_method,
                    'otp' => 'none',
                    'ip' => $s->ip,
                    'hash' => $s->content_hash,
                    'at' => $s->accepted_at?->toIso8601String(),
                    'href' => '/admin/service-orders/'.$s->id,
                ]));
        }

        return Inertia::render('admin/acceptances', [
            'rows' => $rows->sortByDesc('at')->values()->take(200)->all(),
            'filter' => ['q' => $q, 'kind' => $kind, 'from' => $from?->toDateString(), 'to' => $to?->toDateString()],
        ]);
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function venueMatches($query, string $q): void
    {
        $query->where(fn ($w) => $w
            ->where('slug', 'ilike', "%{$q}%")
            ->orWhere('name_ar', 'ilike', "%{$q}%")
            ->orWhere('name_en', 'ilike', "%{$q}%")
            ->orWhere('legal_name', 'ilike', "%{$q}%"));
    }

    private function date(string $value): ?CarbonImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
