<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgreementAcceptance;
use App\Models\AgreementVersion;
use App\Models\AuditLog;
use App\Models\Place;
use App\Services\Agreements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Writing, publishing and reading the Partner Terms.
 *
 * A draft is edited here until it is right, then published, at which point
 * the model itself refuses every further edit. Changing the terms means a
 * new draft, a new version number, and every venue accepting again.
 */
class AgreementController extends Controller
{
    public function __construct(private readonly Agreements $agreements) {}

    public function index(): Response
    {
        return Inertia::render('admin/agreements/index', [
            'versions' => AgreementVersion::query()
                ->ofKind()
                ->withCount('acceptances')
                ->orderByDesc('id')
                ->get()
                ->map(fn (AgreementVersion $version) => $this->summary($version))
                ->all(),
            // Who still has to accept the version in force. Scale, not
            // money -- an administrator sees how many, and which.
            'outstanding' => $this->outstanding(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $version = AgreementVersion::create([...$data, 'kind' => AgreementVersion::KIND_PARTNER_TERMS]);
        $version->forceFill(['created_by' => $request->user()->id])->save();

        AuditLog::record('agreement_drafted', null, ['version' => $version->version]);

        return to_route('admin.agreements.show', $version)
            ->with('success', __('ui.agreement.admin.drafted'));
    }

    public function show(AgreementVersion $agreement): Response
    {
        return Inertia::render('admin/agreements/show', [
            'agreement' => [
                ...$this->summary($agreement),
                'body_ar' => $agreement->body_ar,
                'body_en' => $agreement->body_en,
                'change_note_ar' => $agreement->change_note_ar,
                'change_note_en' => $agreement->change_note_en,
                'content_hash' => $agreement->content_hash,
                'published_by' => $agreement->publisher?->name,
            ],
            'acceptances' => $agreement->acceptances()
                ->with(['place:id,name_ar,name_en,slug', 'user:id,name'])
                ->latest('accepted_at')
                ->get()
                ->map(fn (AgreementAcceptance $acceptance) => [
                    'id' => $acceptance->id,
                    'place_ar' => $acceptance->place->name_ar,
                    'place_en' => $acceptance->place->name_en,
                    'legal_name' => $acceptance->legal_name,
                    'representative_name' => $acceptance->representative_name,
                    'representative_title' => $acceptance->representative_title,
                    'representative_phone' => $acceptance->representative_phone,
                    'accepted_by' => $acceptance->user?->name,
                    'accepted_at' => $acceptance->accepted_at->toIso8601String(),
                    'otp_channel' => $acceptance->otp_channel,
                    'ip' => $acceptance->ip,
                ])
                ->all(),
        ]);
    }

    public function update(Request $request, AgreementVersion $agreement): RedirectResponse
    {
        abort_unless($agreement->isDraft(), 409, 'A published agreement version is immutable.');

        $agreement->update($this->validated($request, $agreement));

        return back()->with('success', __('ui.agreement.admin.saved'));
    }

    public function publish(Request $request, AgreementVersion $agreement): RedirectResponse
    {
        abort_unless($agreement->isDraft(), 409, 'Only a draft can be published.');

        $this->agreements->publish($agreement, $request->user());

        return back()->with('success', __('ui.agreement.admin.published', ['version' => $agreement->version]));
    }

    public function destroy(AgreementVersion $agreement): RedirectResponse
    {
        abort_unless($agreement->isDraft(), 409, 'Only a draft can be deleted.');

        AuditLog::record('agreement_draft_deleted', null, ['version' => $agreement->version]);

        $agreement->delete();

        return to_route('admin.agreements.index')
            ->with('success', __('ui.agreement.admin.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?AgreementVersion $editing = null): array
    {
        return $request->validate([
            'version' => [
                'required', 'string', 'max:20', 'regex:/^\d+(\.\d+){0,2}$/',
                Rule::unique('agreement_versions', 'version')
                    ->where('kind', AgreementVersion::KIND_PARTNER_TERMS)
                    ->ignore($editing?->id),
            ],
            'title_ar' => ['required', 'string', 'max:255'],
            'title_en' => ['required', 'string', 'max:255'],
            'body_ar' => ['required', 'string', 'max:60000'],
            'body_en' => ['required', 'string', 'max:60000'],
            'change_note_ar' => ['nullable', 'string', 'max:2000'],
            'change_note_en' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(AgreementVersion $version): array
    {
        return [
            'id' => $version->id,
            'version' => $version->version,
            'title_ar' => $version->title_ar,
            'title_en' => $version->title_en,
            'status' => $version->status->value,
            'published_at' => $version->published_at?->toIso8601String(),
            'retired_at' => $version->retired_at?->toIso8601String(),
            'acceptances_count' => $version->acceptances_count ?? $version->acceptances()->count(),
        ];
    }

    /**
     * Venues that have not accepted the version in force.
     *
     * @return array{version: string, places: array<int, array{slug: string, name_ar: string, name_en: string, owner: string|null}>}|null
     */
    private function outstanding(): ?array
    {
        $current = AgreementVersion::current();

        if ($current === null) {
            return null;
        }

        $places = Place::query()
            ->where('is_active', true)
            ->whereDoesntHave('acceptances', fn ($query) => $query->where('agreement_version_id', $current->id))
            ->with('user:id,name')
            ->orderBy('name_en')
            ->get()
            ->map(fn (Place $place) => [
                'slug' => $place->slug,
                'name_ar' => $place->name_ar,
                'name_en' => $place->name_en,
                'owner' => $place->user?->name,
            ])
            ->values()
            ->all();

        return ['version' => $current->version, 'places' => $places];
    }
}
