<?php

namespace App\Events;

use App\Models\GraduationPortal;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewGraduationSubmission implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public GraduationPortal $portal,
        public string $submissionId,
        public array $submissionData
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
            new PrivateChannel("department.{$this->portal->department_id}.graduation"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'submission.new';
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
            'portal_name' => $this->portal->name,
            'student_identifier' => $this->submissionData['student_identifier'] ?? 'Anonymous',
            'curriculum_id' => $this->submissionData['curriculum_id'] ?? null,
            'course_count' => $this->submissionData['course_count'] ?? 0,
            'submitted_at' => $this->submissionData['submitted_at'] ?? now()->toIso8601String(),
        ];
    }
}
