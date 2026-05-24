<?php

namespace App\Http\Controllers;

use App\Events\CallAccepted;
use App\Events\CallEnded;
use App\Events\CallRejected;
use App\Events\IncomingCall;
use App\Events\WebRTCSignal;
use App\Models\P2PCall;
use App\Models\P2PCallSignal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class P2PCallController extends Controller
{
    private function dispatchRealtime(object $event, string $context, array $meta = []): void
    {
        try {
            event($event);

            Log::info('P2P realtime event dispatched.', array_merge([
                'context' => $context,
                'event' => class_basename($event),
            ], $meta));
        } catch (Throwable $e) {
            Log::error('P2P realtime event dispatch failed.', array_merge([
                'context' => $context,
                'event' => class_basename($event),
                'error' => $e->getMessage(),
            ], $meta));

            throw $e;
        }
    }

    /**
     * Initiate a call to another user
     */
    public function initiateCall(Request $request)
    {
        $request->validate([
            'callee_uuid' => 'required|string|exists:users,uuid',
        ]);

        $caller = Auth::user();
        $callee = User::where('uuid', $request->callee_uuid)->firstOrFail();

        // Can't call yourself
        if ($caller->id === $callee->id) {
            return response()->json(['error' => 'Cannot call yourself'], 422);
        }

        // Must be in the same business
        if ($caller->business_id !== $callee->business_id) {
            return response()->json(['error' => 'User not in your business'], 403);
        }

        // Check if callee already has an active call
        $activeCall = P2PCall::where(function ($q) use ($callee) {
                $q->where('caller_id', $callee->id)
                  ->orWhere('callee_id', $callee->id);
            })
            ->whereIn('status', ['ringing', 'in_progress'])
            ->first();

        if ($activeCall) {
            return response()->json(['error' => 'User is busy on another call'], 409);
        }

        $call = P2PCall::create([
            'caller_id'   => $caller->id,
            'callee_id'   => $callee->id,
            'business_id' => $caller->business_id,
            'status'      => 'ringing',
            'started_at'  => now(),
        ]);

        $call->load(['caller', 'callee']);

        // Broadcast to callee
        $this->dispatchRealtime(new IncomingCall($call), 'initiate', [
            'call_uuid' => $call->uuid,
            'caller_id' => $caller->id,
            'callee_id' => $callee->id,
        ]);

        return response()->json([
            'call_id' => $call->uuid,
            'callee'  => [
                'id'    => $callee->id,
                'uuid'  => $callee->uuid,
                'name'  => $callee->p2p_name,
                'photo' => $callee->profile_photo_url,
            ],
        ]);
    }

    /**
     * Accept an incoming call
     */
    public function acceptCall($callUuid)
    {
        $call = P2PCall::where('uuid', $callUuid)
            ->where('callee_id', Auth::id())
            ->where('status', 'ringing')
            ->firstOrFail();

        $call->update([
            'status'      => 'in_progress',
            'answered_at' => now(),
        ]);

        $call->load(['caller', 'callee']);

        // Notify the caller
        $this->dispatchRealtime(new CallAccepted($call), 'accept', [
            'call_uuid' => $call->uuid,
            'caller_id' => $call->caller_id,
            'callee_id' => $call->callee_id,
        ]);

        return response()->json(['status' => 'accepted']);
    }

    /**
     * Reject an incoming call
     */
    public function rejectCall($callUuid)
    {
        $call = P2PCall::where('uuid', $callUuid)
            ->where('callee_id', Auth::id())
            ->where('status', 'ringing')
            ->firstOrFail();

        $call->update([
            'status'     => 'rejected',
            'end_reason' => 'rejected',
            'ended_at'   => now(),
        ]);

        // Notify the caller
        $this->dispatchRealtime(new CallRejected($call), 'reject', [
            'call_uuid' => $call->uuid,
            'caller_id' => $call->caller_id,
            'callee_id' => $call->callee_id,
        ]);

        return response()->json(['status' => 'rejected']);
    }

    /**
     * Cancel an outgoing call (caller cancels before answer)
     */
    public function cancelCall($callUuid)
    {
        $call = P2PCall::where('uuid', $callUuid)
            ->where('caller_id', Auth::id())
            ->where('status', 'ringing')
            ->firstOrFail();

        $call->update([
            'status'     => 'cancelled',
            'end_reason' => 'cancelled',
            'ended_at'   => now(),
        ]);

        // Notify the callee
        $this->dispatchRealtime(new CallEnded($call, $call->callee_id), 'cancel', [
            'call_uuid' => $call->uuid,
            'caller_id' => $call->caller_id,
            'callee_id' => $call->callee_id,
            'target_user_id' => $call->callee_id,
        ]);

        return response()->json(['status' => 'cancelled']);
    }

    /**
     * End an active call
     */
    public function endCall($callUuid)
    {
        $user = Auth::user();

        $call = P2PCall::where('uuid', $callUuid)
            ->where(function ($q) use ($user) {
                $q->where('caller_id', $user->id)
                  ->orWhere('callee_id', $user->id);
            })
            ->whereIn('status', ['ringing', 'in_progress'])
            ->firstOrFail();

        $endReason = $call->status === 'ringing' ? 'missed' : 'normal';

        $call->update([
            'status'     => $call->status === 'ringing' ? 'missed' : 'completed',
            'end_reason' => $endReason,
            'ended_at'   => now(),
        ]);

        // Notify the other participant
        $otherUserId = $call->caller_id === $user->id ? $call->callee_id : $call->caller_id;
        $this->dispatchRealtime(new CallEnded($call, $otherUserId), 'end', [
            'call_uuid' => $call->uuid,
            'caller_id' => $call->caller_id,
            'callee_id' => $call->callee_id,
            'target_user_id' => $otherUserId,
        ]);

        return response()->json([
            'status'   => 'ended',
            'duration' => $call->duration,
        ]);
    }

    /**
     * Relay call signaling data (WebRTC offer/answer/candidate or compatibility audio chunks)
     */
    public function signal(Request $request, $callUuid)
    {
        $request->validate([
            'type' => 'required|in:offer,answer,candidate,audio_chunk',
            'data' => 'required|array',
        ]);

        $user = Auth::user();

        $call = P2PCall::where('uuid', $callUuid)
            ->where(function ($q) use ($user) {
                $q->where('caller_id', $user->id)
                  ->orWhere('callee_id', $user->id);
            })
            ->whereIn('status', ['ringing', 'in_progress'])
            ->firstOrFail();

        // Send signal to the other participant
        $targetUserId = $call->caller_id === $user->id ? $call->callee_id : $call->caller_id;

        $signal = P2PCallSignal::create([
            'p2p_call_id' => $call->id,
            'sender_id' => $user->id,
            'receiver_id' => $targetUserId,
            'type' => $request->type,
            'data' => $request->data,
        ]);

        $this->dispatchRealtime(new WebRTCSignal(
            $call->uuid,
            $targetUserId,
            $request->type,
            $request->data,
            $signal->id,
        ), 'signal', [
            'call_uuid' => $call->uuid,
            'caller_id' => $call->caller_id,
            'callee_id' => $call->callee_id,
            'target_user_id' => $targetUserId,
            'signal_type' => $request->type,
        ]);

        return response()->json(['status' => 'sent']);
    }

    /**
     * Poll undelivered WebRTC signals for the current call participant.
     */
    public function pollSignals($callUuid)
    {
        $user = Auth::user();

        $call = P2PCall::where('uuid', $callUuid)
            ->where('business_id', $user->business_id)
            ->where(function ($q) use ($user) {
                $q->where('caller_id', $user->id)
                    ->orWhere('callee_id', $user->id);
            })
            ->firstOrFail();

        $signals = P2PCallSignal::where('p2p_call_id', $call->id)
            ->where('receiver_id', $user->id)
            ->whereNull('delivered_at')
            ->orderBy('id')
            ->get();

        if ($signals->isEmpty()) {
            return response()->json([]);
        }

        P2PCallSignal::whereIn('id', $signals->pluck('id'))
            ->update(['delivered_at' => now()]);

        return response()->json(
            $signals->map(function ($signal) {
                return [
                    'signal_id' => $signal->id,
                    'call_id' => $signal->call->uuid,
                    'type' => $signal->type,
                    'data' => $signal->data,
                ];
            })->values()
        );
    }

    /**
     * Get call history for the current user
     */
    public function callHistory()
    {
        $user = Auth::user();

        $calls = P2PCall::forUser($user->id)
            ->forBusiness($user->business_id)
            ->with(['caller', 'callee'])
            ->latest('created_at')
            ->paginate(20);

        return view('calls.history', compact('calls', 'user'));
    }

    /**
     * Get online users in the same business (AJAX endpoint)
     */
    public function onlineUsers()
    {
        $user = Auth::user();

        // Get all users in the same business (excluding self)
        $users = User::where('business_id', $user->business_id)
            ->where('id', '!=', $user->id)
            ->where('status', 'active')
            ->select('id', 'uuid', 'name', 'p2p_display_name', 'profile_photo_path')
            ->get()
            ->map(function ($u) {
                return [
                    'id'    => $u->id,
                    'uuid'  => $u->uuid,
                    'name'  => $u->p2p_name,
                    'photo' => $u->profile_photo_url,
                ];
            });

        return response()->json($users);
    }

    /**
     * Lightweight fallback for the callee UI when realtime delivery is delayed.
     */
    public function incomingCall()
    {
        $user = Auth::user();

        $call = P2PCall::where('callee_id', $user->id)
            ->where('business_id', $user->business_id)
            ->where('status', 'ringing')
            ->with('caller')
            ->latest('started_at')
            ->first();

        if (! $call) {
            return response()->json([
                'call_id' => null,
            ]);
        }

        return response()->json([
            'call_id' => $call->uuid,
            'caller' => [
                'id' => $call->caller->id,
                'uuid' => $call->caller->uuid,
                'name' => $call->caller->p2p_name,
                'photo' => $call->caller->profile_photo_url,
            ],
            'started_at' => optional($call->started_at)->toISOString(),
        ]);
    }

    /**
     * Reconcile the current call state from the database as a fallback when
     * realtime events are delayed or missed by the browser.
     */
    public function callStatus($callUuid)
    {
        $user = Auth::user();

        $call = P2PCall::where('uuid', $callUuid)
            ->where('business_id', $user->business_id)
            ->where(function ($q) use ($user) {
                $q->where('caller_id', $user->id)
                    ->orWhere('callee_id', $user->id);
            })
            ->with(['caller', 'callee'])
            ->firstOrFail();

        $otherUser = $call->caller_id === $user->id ? $call->callee : $call->caller;

        return response()->json([
            'call_id' => $call->uuid,
            'status' => $call->status,
            'is_caller' => $call->caller_id === $user->id,
            'answered_at' => optional($call->answered_at)->toISOString(),
            'ended_at' => optional($call->ended_at)->toISOString(),
            'end_reason' => $call->end_reason,
            'other_user' => [
                'id' => $otherUser->id,
                'uuid' => $otherUser->uuid,
                'name' => $otherUser->p2p_name,
                'photo' => $otherUser->profile_photo_url,
            ],
        ]);
    }
}
