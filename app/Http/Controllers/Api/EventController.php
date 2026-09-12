<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventDonation;
use App\Models\FundraisingEvent;
use App\Models\Member;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventController extends Controller
{
    public function __construct(private PushNotificationService $push) {}
    public function index(Request $request)
    {
        $query = FundraisingEvent::query()->orderByDesc('starts_at');
        if ($request->boolean('active_only')) {
            $query->where('status', 'active');
        }

        return response()->json([
            'data' => $query->get()->map(fn (FundraisingEvent $e) => $this->transform($e)),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string'],
            'goal_amount' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ]);

        $event = FundraisingEvent::query()->create([
            'title' => $data['title'],
            'slug' => Str::slug($data['title']).'-'.Str::random(4),
            'description' => $data['description'] ?? null,
            'goal_amount' => $data['goal_amount'],
            'raised_amount' => 0,
            'donor_count' => 0,
            'starts_at' => $data['starts_at'] ?? now(),
            'ends_at' => $data['ends_at'] ?? null,
            'status' => 'active',
            'created_by' => $request->user()?->id,
        ]);

        return response()->json($this->transform($event), 201);
    }

    public function donations(Request $request)
    {
        $query = EventDonation::query()->with(['event', 'referredBy'])->latest('payment_date');

        if ($request->filled('member_id')) {
            $member = Member::query()->find($request->integer('member_id'));
            if (! $member) {
                return response()->json(['data' => []]);
            }
            $query->where(function ($q) use ($member) {
                $q->where('member_id', $member->id);
                if ($member->phone) {
                    $q->orWhere('donor_phone', $member->phone);
                }
            });
        }

        $user = $request->user();
        if ($user && ! $user->isStaff() && $user->member_id) {
            $own = Member::query()->find($user->member_id);
            $query->where(function ($q) use ($user, $own) {
                $q->where('member_id', $user->member_id);
                if ($own?->phone) {
                    $q->orWhere('donor_phone', $own->phone);
                }
            });
        }

        return response()->json([
            'data' => $query->limit(100)->get()->map(fn (EventDonation $d) => $this->transformDonation($d)),
        ]);
    }

    public function donate(Request $request, FundraisingEvent $event)
    {
        $data = $request->validate([
            'donor_type' => ['required', 'in:member,non_member'],
            'donor_name' => ['required', 'string'],
            'donor_phone' => ['nullable', 'string'],
            'amount' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'string'],
            'member_id' => ['nullable', 'exists:members,id'],
            'referred_by_member_id' => ['nullable', 'exists:members,id'],
            'idempotency_key' => ['nullable', 'string'],
        ]);

        $memberId = $data['member_id'] ?? null;
        if ($data['donor_type'] === 'member' && ! $memberId && ! empty($data['donor_phone'])) {
            $memberId = Member::findByPhone($data['donor_phone'])?->id;
        }
        if ($data['donor_type'] !== 'member') {
            $memberId = null;
        }

        $key = $data['idempotency_key'] ?? (string) Str::uuid();
        if ($existing = EventDonation::query()->where('idempotency_key', $key)->first()) {
            return response()->json($this->transformDonation($existing->load('event', 'referredBy')));
        }

        $donation = DB::transaction(function () use ($event, $data, $key, $request, $memberId) {
            $donation = EventDonation::query()->create([
                'receipt_number' => $this->nextDonationReceiptNumber(),
                'fundraising_event_id' => $event->id,
                'donor_type' => $data['donor_type'],
                'member_id' => $memberId,
                'donor_name' => $data['donor_name'],
                'donor_phone' => $data['donor_phone'] ?? null,
                'referred_by_member_id' => $data['referred_by_member_id'] ?? null,
                'amount' => $data['amount'],
                'payment_method' => $data['payment_method'],
                'payment_date' => now(),
                'status' => 'confirmed',
                'idempotency_key' => $key,
                'created_by' => $request->user()?->id,
            ]);

            $event->increment('raised_amount', $data['amount']);
            $event->increment('donor_count');

            return $donation->load('event', 'referredBy');
        });

        $this->push->notifyStaff(
            'Donation received',
            $donation->donor_name.' donated ৳'.number_format((int) $donation->amount).' to '.$event->title,
            ['type' => 'donation', 'route' => '/events'],
        );

        return response()->json($this->transformDonation($donation), 201);
    }

    public function publicShow(string $slug)
    {
        $event = FundraisingEvent::query()->where('slug', $slug)->firstOrFail();

        return response()->json($this->transform($event));
    }

    public function publicDonate(Request $request, string $slug)
    {
        $event = FundraisingEvent::query()->where('slug', $slug)->where('status', 'active')->firstOrFail();

        if ($code = $request->string('referral_code')->toString()) {
            $member = Member::query()->where('referral_code', $code)->first();
            if ($member) {
                $request->merge(['referred_by_member_id' => $member->id]);
            }
        }

        $request->merge(['donor_type' => 'non_member']);

        return $this->donate($request, $event);
    }

    private function transform(FundraisingEvent $e): array
    {
        return [
            'id' => (string) $e->id,
            'title' => $e->title,
            'slug' => $e->slug,
            'description' => $e->description,
            'goal_amount' => $e->goal_amount,
            'raised_amount' => $e->raised_amount,
            'donor_count' => $e->donor_count,
            'status' => $e->status,
            'starts_at' => $e->starts_at?->toIso8601String(),
            'ends_at' => $e->ends_at?->toIso8601String(),
        ];
    }

    private function transformDonation(EventDonation $d): array
    {
        return [
            'id' => (string) $d->id,
            'receipt_number' => $d->receipt_number,
            'event_id' => (string) $d->fundraising_event_id,
            'event_title' => $d->event?->title,
            'donor_type' => $d->donor_type,
            'donor_name' => $d->donor_name,
            'donor_phone' => $d->donor_phone,
            'member_id' => $d->member_id ? (string) $d->member_id : null,
            'amount' => $d->amount,
            'payment_method' => $d->payment_method,
            'payment_date' => $d->payment_date?->toIso8601String(),
            'referred_by_name' => $d->referredBy?->name,
        ];
    }

    private function nextDonationReceiptNumber(): string
    {
        $max = 20000;
        foreach (EventDonation::query()->lockForUpdate()->pluck('receipt_number') as $receipt) {
            if (is_string($receipt) && preg_match('/^D-(\d+)$/', $receipt, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return 'D-'.($max + 1);
    }
}
