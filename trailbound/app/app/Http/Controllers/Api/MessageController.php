<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityEvent;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Friend;
use App\Models\Message;
use App\Models\User;
use App\Support\Jwt;
use App\Support\Realtime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $conversationIds = ConversationParticipant::query()
            ->where('user_id', $user->id)
            ->pluck('conversation_id');

        $conversations = Conversation::query()
            ->with(['participants.user.profile', 'messages' => fn ($query) => $query->latest()->limit(1)])
            ->whereIn('id', $conversationIds)
            ->latest('last_message_at')
            ->get()
            ->map(fn (Conversation $conversation) => $this->conversationPayload($conversation, $user->id));

        return response()->json(['conversations' => $conversations]);
    }

    public function show(Request $request, int $conversationId): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user || ! $this->isParticipant($conversationId, $user->id)) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        ConversationParticipant::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $user->id)
            ->update(['last_read_at' => now()]);

        $messages = Message::query()
            ->with('user.profile')
            ->where('conversation_id', $conversationId)
            ->latest()
            ->limit(80)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (Message $message) => $this->messagePayload($message));

        return response()->json(['messages' => $messages]);
    }

    public function start(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $data = $request->validate([
            'friend_id' => ['required', 'integer', 'exists:users,id'],
            'body' => ['nullable', 'string', 'max:1200'],
        ]);

        if (! $this->areFriends($user->id, (int) $data['friend_id'])) {
            return response()->json(['message' => 'You can only message accepted friends.'], 403);
        }

        $conversation = $this->directConversation($user->id, (int) $data['friend_id']);
        if (! empty($data['body'])) {
            $this->createMessage($conversation, $user->id, $data['body']);
        }

        return response()->json([
            'conversation' => $this->conversationPayload($conversation->fresh(['participants.user.profile', 'messages']), $user->id),
        ]);
    }

    public function send(Request $request, int $conversationId): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user || ! $this->isParticipant($conversationId, $user->id)) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        $data = $request->validate(['body' => ['required', 'string', 'max:1200']]);
        $conversation = Conversation::query()->findOrFail($conversationId);
        $message = $this->createMessage($conversation, $user->id, $data['body']);

        return response()->json(['message' => $this->messagePayload($message->fresh('user.profile'))]);
    }

    private function directConversation(int $a, int $b): Conversation
    {
        $existing = Conversation::query()
            ->where('type', 'direct')
            ->whereHas('participants', fn ($query) => $query->where('user_id', $a))
            ->whereHas('participants', fn ($query) => $query->where('user_id', $b))
            ->first();

        if ($existing) {
            return $existing;
        }

        $conversation = Conversation::query()->create(['type' => 'direct', 'last_message_at' => now()]);
        ConversationParticipant::query()->create(['conversation_id' => $conversation->id, 'user_id' => $a, 'last_read_at' => now()]);
        ConversationParticipant::query()->create(['conversation_id' => $conversation->id, 'user_id' => $b]);

        return $conversation;
    }

    private function createMessage(Conversation $conversation, int $userId, string $body): Message
    {
        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $userId,
            'body' => $body,
        ]);

        $conversation->update(['last_message_at' => now()]);
        ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $userId)
            ->update(['last_read_at' => now()]);

        ActivityEvent::query()->create([
            'user_id' => $userId,
            'type' => 'message_sent',
            'payload' => ['conversation_id' => $conversation->id],
        ]);

        Realtime::publish('messages.updated', ['conversation_id' => $conversation->id, 'user_id' => $userId]);
        Realtime::publish('notifications.updated', ['reason' => 'message', 'user_id' => $userId]);

        return $message;
    }

    private function conversationPayload(Conversation $conversation, int $viewerId): array
    {
        $others = $conversation->participants
            ->filter(fn (ConversationParticipant $participant) => $participant->user_id !== $viewerId)
            ->map(fn (ConversationParticipant $participant) => [
                'id' => $participant->user->id,
                'name' => $participant->user->name,
                'display_name' => $participant->user->profile?->display_name,
                'avatar_path' => $participant->user->profile?->avatar_path,
                'runner_type' => $participant->user->profile?->runner_type,
            ])
            ->values();

        $last = $conversation->messages->first();
        $participant = $conversation->participants->firstWhere('user_id', $viewerId);

        return [
            'id' => $conversation->id,
            'type' => $conversation->type,
            'others' => $others,
            'last_message' => $last ? $this->messagePayload($last) : null,
            'last_message_at' => $conversation->last_message_at,
            'unread' => $participant && $last && (! $participant->last_read_at || $last->created_at->gt($participant->last_read_at)),
        ];
    }

    private function messagePayload(Message $message): array
    {
        return [
            'id' => $message->id,
            'body' => $message->body,
            'created_at' => $message->created_at,
            'user' => [
                'id' => $message->user->id,
                'name' => $message->user->name,
                'display_name' => $message->user->profile?->display_name,
                'avatar_path' => $message->user->profile?->avatar_path,
            ],
        ];
    }

    private function isParticipant(int $conversationId, int $userId): bool
    {
        return ConversationParticipant::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->exists();
    }

    private function areFriends(int $a, int $b): bool
    {
        return Friend::query()
            ->where('user_id', $a)
            ->where('friend_id', $b)
            ->where('status', 'accepted')
            ->exists();
    }
}
