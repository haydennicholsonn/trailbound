<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'friend_id', 'nickname'])]
class FriendNickname extends Model
{
    protected $table = 'friend_nicknames';
}
