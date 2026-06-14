<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\BadgeController;
use App\Http\Controllers\Api\ChallengeController;
use App\Http\Controllers\Api\FeedController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\ShopController;
use App\Http\Controllers\Api\SkillController;
use App\Http\Controllers\Api\BeaconController;
use App\Http\Controllers\Api\FriendController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OnlineController;
use App\Http\Controllers\Api\StatusController;
use App\Http\Controllers\Api\StravaController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\WorldController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'ok' => true,
    'app' => 'Trailbound',
    'time' => now()->toIso8601String(),
]));

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/logout', [AuthController::class, 'logout']);
Route::get('/auth/me', [AuthController::class, 'me']);
Route::post('/auth/detect-shard', [AuthController::class, 'detectShard']);
Route::patch('/profile', [AuthController::class, 'updateProfile']);
Route::post('/profile/avatar', [AuthController::class, 'uploadAvatar']);
Route::post('/profile/background', [AuthController::class, 'uploadBackground']);
Route::patch('/profile/bio', [AuthController::class, 'updateBio']);
Route::get('/world', [WorldController::class, 'show']);
Route::post('/runs', [WorldController::class, 'storeRun']);
Route::get('/runs/{runId}', [WorldController::class, 'showRun']);
Route::post('/runs/{runId}/images', [WorldController::class, 'uploadImages']);
Route::get('/auth/google', [AuthController::class, 'googleRedirect']);
Route::get('/auth/google/callback', [AuthController::class, 'googleCallback']);

Route::get('/strava/connect', [StravaController::class, 'connect']);
Route::post('/strava/callback', [StravaController::class, 'callback']);
Route::get('/strava/status', [StravaController::class, 'status']);
Route::post('/strava/sync', [StravaController::class, 'sync']);
Route::post('/strava/disconnect', [StravaController::class, 'disconnect']);

Route::get('/friends', [FriendController::class, 'index']);
Route::post('/friends/request', [FriendController::class, 'request']);
Route::post('/friends/cancel', [FriendController::class, 'cancel']);
Route::post('/friends/accept', [FriendController::class, 'accept']);
Route::post('/friends/reject', [FriendController::class, 'reject']);
Route::delete('/friends/{friendId}', [FriendController::class, 'remove']);
Route::patch('/friends/{friendId}/nickname', [FriendController::class, 'updateNickname']);
Route::patch('/friends/{friendId}/preference', [FriendController::class, 'updatePreference']);

Route::get('/status', [StatusController::class, 'show']);
Route::post('/status', [StatusController::class, 'store']);
Route::get('/users/{userId}/status', [StatusController::class, 'userStatus']);

Route::get('/feed', [FeedController::class, 'index']);
Route::post('/feed/{eventId}/reaction', [FeedController::class, 'react']);
Route::post('/feed/{eventId}/comments', [FeedController::class, 'comment']);

Route::get('/online', [OnlineController::class, 'index']);
Route::post('/online/heartbeat', [OnlineController::class, 'heartbeat']);

Route::post('/location/heartbeat', [LocationController::class, 'heartbeat']);
Route::get('/locations/friends', [LocationController::class, 'friends']);

Route::get('/beacons', [BeaconController::class, 'index']);
Route::post('/beacons', [BeaconController::class, 'store']);

Route::get('/messages', [MessageController::class, 'index']);
Route::post('/messages/start', [MessageController::class, 'start']);
Route::get('/messages/{conversationId}', [MessageController::class, 'show']);
Route::post('/messages/{conversationId}', [MessageController::class, 'send']);

Route::get('/notifications', [NotificationController::class, 'index']);
Route::post('/notifications/{notificationId}/read', [NotificationController::class, 'read']);
Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
Route::get('/notifications/preferences', [NotificationController::class, 'preferences']);
Route::patch('/notifications/preferences', [NotificationController::class, 'updatePreferences']);
Route::get('/admin/stats', [AdminController::class, 'stats']);
Route::get('/admin/players', [AdminController::class, 'players']);
Route::patch('/admin/players/{playerId}', [AdminController::class, 'updatePlayer']);
Route::post('/admin/players/{playerId}/tears', [AdminController::class, 'adjustTears']);

Route::get('/wallet', [WalletController::class, 'balance']);
Route::post('/wallet/top-up', [WalletController::class, 'topUp']);
Route::get('/shop', [ShopController::class, 'index']);
Route::post('/shop/{shopItemId}/buy', [ShopController::class, 'buy']);
Route::get('/inventory', [InventoryController::class, 'index']);
Route::get('/skills/tree', [SkillController::class, 'tree']);
Route::post('/skills/{nodeId}/unlock', [SkillController::class, 'unlock']);
Route::post('/skills/respec', [SkillController::class, 'respec']);
Route::get('/challenges/official', [ChallengeController::class, 'official']);
Route::get('/challenges/friends', [ChallengeController::class, 'friendChallenges']);
Route::post('/challenges', [ChallengeController::class, 'create']);
Route::post('/challenges/{challengeId}/accept', [ChallengeController::class, 'accept']);
Route::post('/challenges/{challengeId}/decline', [ChallengeController::class, 'decline']);
Route::post('/challenges/{challengeId}/claim', [ChallengeController::class, 'claim']);
Route::get('/badges', [BadgeController::class, 'index']);
Route::get('/packages', [PackageController::class, 'index']);
Route::get('/packages/current', [PackageController::class, 'current']);
Route::post('/packages/select', [PackageController::class, 'select']);
Route::get('/admin/packages', [PackageController::class, 'adminIndex']);
Route::post('/admin/packages', [PackageController::class, 'adminStore']);
Route::patch('/admin/packages/{packageId}', [PackageController::class, 'adminUpdate']);
Route::get('/admin/challenges', [ChallengeController::class, 'adminIndex']);

Route::match(['get', 'post'], '/strava/webhook', [StravaController::class, 'webhook']);
