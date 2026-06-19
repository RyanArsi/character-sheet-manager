<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CampaignInitiativeUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string,mixed>  $state  Estado completo da iniciativa
     */
    public function __construct(public int $campaignId, public array $state)
    {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('campaign.'.$this->campaignId);
    }

    public function broadcastAs(): string
    {
        return 'CampaignInitiativeUpdated';
    }

    /** @return array<string,mixed> */
    public function broadcastWith(): array
    {
        return ['state' => $this->state];
    }
}
