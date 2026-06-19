<?php

use App\Models\Campaign;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Só membros da campanha podem ouvir o feed de eventos dela.
Broadcast::channel('campaign.{campaign}', function ($user, Campaign $campaign) {
    return $campaign->members()->where('users.id', $user->id)->exists();
});
