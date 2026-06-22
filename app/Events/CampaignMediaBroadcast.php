<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Pede que todos os participantes da campanha reproduzam uma mídia (som/vídeo)
 * a partir da sua URL pública. Trafega apenas a URL — o arquivo NÃO é enviado
 * pelo WebSocket; cada cliente busca a mídia direto do servidor de assets.
 * Evento efêmero: não persiste nem aparece no feed.
 */
class CampaignMediaBroadcast implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $campaignId,
        public string $url,
        public int $volume = 100,
    ) {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('campaign.'.$this->campaignId);
    }

    public function broadcastAs(): string
    {
        return 'CampaignMediaBroadcast';
    }

    /** @return array<string,mixed> */
    public function broadcastWith(): array
    {
        return [
            'url'    => $this->url,
            'volume' => $this->volume,
        ];
    }
}
