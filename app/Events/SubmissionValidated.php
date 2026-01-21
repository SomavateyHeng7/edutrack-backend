<?php

namespace App\Events;

use App\Models\GraduationPortal;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubmissionValidated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public GraduationPortal $portal,
        public string $submissionId,
        public array $validationResult
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("graduation-portal.{$this->portal->id}"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'submission.validated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'submission_id' => $this->submissionId,
            'portal_id' => $this->portal->id,
            'can_graduate' => $this->validationResult['canGraduate'] ?? false,
            'summary' => $this->validationResult['summary'] ?? [],
            'error_count' => count($this->validationResult['errors'] ?? []),
            'warning_count' => count($this->validationResult['warnings'] ?? []),
            'validated_at' => now()->toIso8601String(),
        ];
    }
}
