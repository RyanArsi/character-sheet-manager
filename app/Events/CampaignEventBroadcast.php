<?php

namespace App\Events;

use App\Models\CampaignEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CampaignEventBroadcast implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public CampaignEvent $event)
    {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('campaign.'.$this->event->campaign_id);
    }

    public function broadcastAs(): string
    {
        return 'CampaignEventBroadcast';
    }

    /** @return array<string,mixed> */
    public function broadcastWith(): array
    {
        return [
            'id'      => $this->event->id,
            'actor'   => $this->event->actor,
            'message' => $this->event->message,
            'detail'  => $this->event->detail,
            'time'    => $this->event->created_at?->format('H:i:s'),
        ];
    }
}
