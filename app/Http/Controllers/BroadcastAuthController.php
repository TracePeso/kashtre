<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Pusher\Pusher;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

class BroadcastAuthController extends Controller
{
    protected function makePusher(): Pusher
    {
        $config = config('broadcasting.connections.reverb');

        return new Pusher(
            $config['key'],
            $config['secret'],
            $config['app_id'],
            $config['options'] ?? []
        );
    }

    protected function jsonStringResponse(string $payload): SymfonyResponse
    {
        return response($payload, 200, ['Content-Type' => 'application/json']);
    }

    protected function authorizeKnownChannel(Request $request): ?SymfonyResponse
    {
        $channelName = (string) $request->input('channel_name', '');
        $socketId = (string) $request->input('socket_id', '');
        $user = $request->user();

        if (! $user || $channelName === '' || $socketId === '') {
            return null;
        }

        $pusher = $this->makePusher();

        if (preg_match('/^private-user\.(\d+)$/', $channelName, $matches)) {
            if ((int) $user->id !== (int) $matches[1]) {
                throw new AccessDeniedHttpException;
            }

            return $this->jsonStringResponse(
                $pusher->authorizeChannel($channelName, $socketId)
            );
        }

        if (preg_match('/^private-App\.Models\.User\.(\d+)$/', $channelName, $matches)) {
            if ((int) $user->id !== (int) $matches[1]) {
                throw new AccessDeniedHttpException;
            }

            return $this->jsonStringResponse(
                $pusher->authorizeChannel($channelName, $socketId)
            );
        }

        if (preg_match('/^(?:presence-)?presence-business\.(\d+)$/', $channelName, $matches)) {
            if ((int) ($user->business_id ?? 0) !== (int) $matches[1]) {
                throw new AccessDeniedHttpException;
            }

            return $this->jsonStringResponse(
                $pusher->authorizePresenceChannel($channelName, $socketId, (string) $user->getAuthIdentifier(), [
                    'id' => (int) $user->id,
                    'uuid' => (string) ($user->uuid ?? ''),
                    'name' => (string) ($user->name ?? ''),
                    'photo' => (string) ($user->profile_photo_url ?? ''),
                ])
            );
        }

        return null;
    }

    protected function shouldExposeError(Request $request): bool
    {
        return str_starts_with($request->getHost(), 'staging.');
    }

    public function __invoke(Request $request): JsonResponse|SymfonyResponse
    {
        try {
            if ($knownChannelResponse = $this->authorizeKnownChannel($request)) {
                return $knownChannelResponse;
            }

            $response = Broadcast::auth($request);

            if ($response instanceof SymfonyResponse) {
                return $response;
            }

            if (is_array($response)) {
                return response()->json($response);
            }

            if (is_string($response) && $response !== '') {
                return response($response, 200, ['Content-Type' => 'application/json']);
            }

            Log::warning('Broadcast auth returned an empty response.', [
                'user_id' => optional($request->user())->id,
                'channel_name' => $request->input('channel_name'),
                'socket_id' => $request->input('socket_id'),
            ]);

            $payload = [
                'message' => 'Broadcast authorization returned an empty response.',
            ];

            if ($this->shouldExposeError($request)) {
                $payload['error'] = 'Empty authorization payload returned by Broadcast::auth().';
            }

            return response()->json($payload, 500);
        } catch (AccessDeniedHttpException $e) {
            return response()->json([
                'message' => 'You are not authorized to join this channel.',
            ], 403);
        } catch (Throwable $e) {
            Log::error('Broadcast auth failed.', [
                'message' => $e->getMessage(),
                'user_id' => optional($request->user())->id,
                'channel_name' => $request->input('channel_name'),
                'socket_id' => $request->input('socket_id'),
            ]);

            $payload = [
                'message' => 'Broadcast authorization failed.',
            ];

            if ($this->shouldExposeError($request)) {
                $payload['error'] = $e->getMessage();
                $payload['exception'] = class_basename($e);
            }

            return response()->json($payload, 500);
        }
    }
}
