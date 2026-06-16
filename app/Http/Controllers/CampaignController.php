<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Character;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $campaigns = $user->campaigns()
            ->with('owner')
            ->withCount('characters')
            ->orderByDesc('campaigns.updated_at')
            ->get();

        return view('campaigns.index', compact('campaigns'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $campaign = Campaign::create([
            'owner_id'    => auth()->id(),
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        $campaign->members()->attach(auth()->id(), ['role' => 'owner']);

        return redirect()->route('campanhas.ver', $campaign);
    }

    public function show(Campaign $campaign): View
    {
        $this->authorizeMember($campaign);

        $campaign->load(['owner', 'members', 'characters.user']);

        $isOwner = $campaign->owner_id === auth()->id();

        return view('campaigns.show', compact('campaign', 'isOwner'));
    }

    public function regenerateToken(Campaign $campaign): RedirectResponse
    {
        $this->authorizeOwner($campaign);

        $campaign->update(['invite_token' => Campaign::generateInviteToken()]);

        return redirect()->route('campanhas.ver', $campaign)
            ->with('status', 'Novo link de convite gerado.');
    }

    public function removeCharacter(Campaign $campaign, Character $character): RedirectResponse
    {
        // O dono da campanha ou o dono da ficha podem remover.
        abort_unless(
            $campaign->owner_id === auth()->id() || $character->user_id === auth()->id(),
            403
        );

        $campaign->characters()->detach($character->id);

        return redirect()->route('campanhas.ver', $campaign)
            ->with('status', 'Ficha removida da campanha.');
    }

    public function banMember(Campaign $campaign, User $user): RedirectResponse
    {
        $this->authorizeOwner($campaign);

        // O mestre não pode banir a si mesmo.
        abort_if($user->id === $campaign->owner_id, 403);

        // Remove as fichas do jogador desta campanha e tira ele da campanha.
        $characterIds = $user->characters()->pluck('id');
        $campaign->characters()->detach($characterIds);
        $campaign->members()->detach($user->id);

        return redirect()->route('campanhas.ver', $campaign)
            ->with('status', "{$user->name} foi removido da campanha.");
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        $this->authorizeOwner($campaign);

        $campaign->delete();

        return redirect()->route('campanhas.index')
            ->with('status', 'Campanha excluída.');
    }

    private function authorizeOwner(Campaign $campaign): void
    {
        abort_unless($campaign->owner_id === auth()->id(), 403);
    }

    private function authorizeMember(Campaign $campaign): void
    {
        abort_unless(
            $campaign->owner_id === auth()->id()
                || $campaign->members()->where('users.id', auth()->id())->exists(),
            403
        );
    }
}
