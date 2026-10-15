<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\TwitchStreamerStreamStartedNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Send a test notification to a specific user')]
#[Signature('app:test-notification')]
final class SendNotification extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $user = User::query()->where(['email' => 'mishaa.pro@proton.me'])->firstOrFail();

        $user->notifyNow(new TwitchStreamerStreamStartedNotification('testBroadcaster'));

        $this->info(sprintf('Notification sent to user %s', $user->email));

    }
}
