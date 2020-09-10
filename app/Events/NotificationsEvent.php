<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

//class NotificationsEvent implements ShouldBroadcast
class NotificationsEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    private $role_id;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($role_id, $message)
    {
        $this->role_id = $role_id;
        $this->message = $message;
    }

//    /**
//     * Get the channels the event should broadcast on.
//     *
//     * @return Channel|array
//     */
//    public function broadcastOn()
//    {
//        return new Channel('role.' . $this->role_id);
//    }
//
//    public function broadcastAs()
//    {
//        return 'notification.event';
//    }
//
//    public function broadcastWith()
//    {
//        return ['message' => $this->message];
//    }
}
