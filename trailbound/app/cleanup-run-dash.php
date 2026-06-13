<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$users = App\Models\User::where('email', 'like', 'run-dash-%@example.com')->get();
foreach ($users as $user) { $user->delete(); }
echo 'remaining=' . App\Models\User::where('email', 'like', 'run-dash-%@example.com')->count() . PHP_EOL;
