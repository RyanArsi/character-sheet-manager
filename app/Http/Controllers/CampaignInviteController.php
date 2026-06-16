<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignInviteController extends Controller
{
    /**
     * Página de convite: mostra a campanha e deixa o usuário escolher
     * quais fichas dele quer adicionar.
     */
    public function show(string $token): View
    {
        $campaign = Campaign::where('invite_token', $token)->firstOrFail();
        $campaign->load('owner');

        $user = auth()->user();

        $myCharacters = $user->characters;
        $alreadyIn = $campaign->characters()
            ->whereIn('characters.id', $myCharacters->pluck('id'))
            ->pluck('characters.id')
            ->all();

        return view('campaigns.invite', compact('campaign', 'myCharacters', 'alreadyIn'));
    }

    /**
     * Adiciona as fichas selecionadas e entra na campanha.
     */
    public function join(Request $request, string $token): RedirectResponse
    {
        $campaign = Campaign::where('invite_token', $token)->firstOrFail();

        $data = $request->validate([
            'characters'   => ['array'],
            'characters.*' => ['integer'],
        ]);

        $user = auth()->user();

        // Só fichas que pertencem ao próprio usuário.
        $characterIds = $user->characters()
            ->whereIn('id', $data['characters'] ?? [])
            ->pluck('id')
            ->all();

        if (! empty($characterIds)) {
            $campaign->characters()->syncWithoutDetaching($characterIds);
        }

        // Garante que o usuário é membro da campanha.
        if (! $campaign->members()->where('users.id', $user->id)->exists()) {
            $role = $campaign->owner_id === $user->id ? 'owner' : 'player';
            $campaign->members()->attach($user->id, ['role' => $role]);
        }

        return redirect()->route('campanhas.ver', $campaign)
            ->with('status', 'Você entrou na campanha!');
    }
}
