<?php

namespace App\Events\Telegram;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewTelegramMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Message $message)
    {
        \Log::info('Broadcasting message to user: ' . $this->message);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channel = 'user.' . $this->message->contact_id;
        \Log::info('Broadcasting to channel: ' . $channel);

        return [
            new Channel($channel)
        ];
    }


    public function broadcastAs(): string
    {
        return 'new_message';
    }

    public function broadcastWith()
    {
        $data = $this->message->toArray();
        return [
            'message' => $data
        ];
    }
}
