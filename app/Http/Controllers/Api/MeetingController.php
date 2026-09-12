<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\OrganizationSetting;
use App\Services\MeetingAnnouncement;
use App\Services\PushNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MeetingController extends Controller
{
    public function __construct(private PushNotificationService $push) {}

    public function index(Request $request)
    {
        $query = Meeting::query()->with(['attendees'])->orderByDesc('starts_at');
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json([
            'data' => $query->limit(100)->get()->map(fn (Meeting $m) => $this->transform($m)),
        ]);
    }

    public function show(Meeting $meeting)
    {
        $meeting->load('attendees');

        return response()->json($this->transform($meeting));
    }

    public function store(Request $request)
    {
        if (! $this->isStaff($request)) {
            return response()->json(['message' => 'Only admin/collector can open a meeting.'], 403);
        }

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:160'],
            'purpose' => ['required', 'string', 'max:2000'],
            'starts_at' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $meeting = Meeting::query()->create([
            'title' => ($data['title'] ?? '') !== '' ? $data['title'] : 'Organization Meeting',
            'purpose' => $data['purpose'],
            'starts_at' => $data['starts_at'],
            'location' => $data['location'] ?? null,
            'status' => 'scheduled',
            'created_by' => $request->user()?->id,
        ]);
        $meeting->load('attendees');

        $when = Carbon::parse($meeting->starts_at)->timezone('Asia/Dhaka');
        $this->push->notifyAll(
            'Meeting scheduled',
            $meeting->purpose.' · '.$when->format('d M Y, h:i A'),
            [
                'type' => 'meeting',
                'route' => '/meetings/'.$meeting->id,
            ],
        );

        return response()->json($this->transform($meeting), 201);
    }

    public function update(Request $request, Meeting $meeting)
    {
        if (! $this->isStaff($request)) {
            return response()->json(['message' => 'Only admin/collector can update a meeting.'], 403);
        }

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:160'],
            'purpose' => ['sometimes', 'string', 'max:2000'],
            'starts_at' => ['sometimes', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:scheduled,completed,cancelled'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'present_member_ids' => ['sometimes', 'array'],
            'present_member_ids.*' => ['integer', 'exists:members,id'],
        ]);

        if (array_key_exists('status', $data) && $meeting->status !== 'scheduled' && $data['status'] !== $meeting->status) {
            return response()->json(['message' => 'This meeting is already closed.'], 422);
        }

        if (($data['status'] ?? null) === 'completed' && trim((string) ($data['summary'] ?? $meeting->summary)) === '') {
            return response()->json(['message' => 'Add a meeting summary before marking it complete.'], 422);
        }

        if (array_key_exists('title', $data)) {
            $meeting->title = $data['title'];
        }
        if (array_key_exists('purpose', $data)) {
            $meeting->purpose = $data['purpose'];
        }
        if (array_key_exists('starts_at', $data)) {
            $meeting->starts_at = $data['starts_at'];
        }
        if (array_key_exists('location', $data)) {
            $meeting->location = $data['location'];
        }
        if (array_key_exists('summary', $data)) {
            $meeting->summary = $data['summary'];
        }
        if (array_key_exists('status', $data)) {
            $meeting->status = $data['status'];
        }

        if (in_array($meeting->status, ['completed', 'cancelled'], true) && ! $meeting->closed_at) {
            $meeting->closed_at = now();
        }

        $meeting->save();

        if (array_key_exists('present_member_ids', $data)) {
            $meeting->attendees()->sync($data['present_member_ids']);
        }

        $meeting->load('attendees');

        return response()->json($this->transform($meeting));
    }

    private function isStaff(Request $request): bool
    {
        return $request->user()?->isStaff() === true;
    }

    private function transform(Meeting $m): array
    {
        $org = OrganizationSetting::current();

        return [
            'id' => (string) $m->id,
            'title' => $m->title,
            'purpose' => $m->purpose,
            'starts_at' => $m->starts_at?->toIso8601String(),
            'location' => $m->location,
            'status' => $m->status,
            'summary' => $m->summary,
            'closed_at' => $m->closed_at?->toIso8601String(),
            'created_at' => $m->created_at?->toIso8601String(),
            'present_member_ids' => $m->attendees->map(fn ($a) => (string) $a->id)->values(),
            'present_members' => $m->attendees->map(fn ($a) => [
                'id' => (string) $a->id,
                'name' => $a->name,
                'member_code' => $a->member_code,
            ])->values(),
            'announcement_en' => MeetingAnnouncement::english($m, $org),
            'announcement_bn' => MeetingAnnouncement::bangla($m, $org),
        ];
    }
}
