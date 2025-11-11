<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\TwitchStreamerStreamStartedNotification;
use Illuminate\Console\Command;

class SendNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-notification';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test notification to a specific user';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $user = User::where(['email' => 'mishaa.pro@proton.me'])->firstOrFail();

        $user->notifyNow(new TwitchStreamerStreamStartedNotification('testBroadcaster'));

        $this->info(sprintf('Notification sent to user %s', $user->email));

    }
}
