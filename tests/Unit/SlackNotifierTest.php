<?php

namespace Tests\Unit;

use App\Services\SlackNotifier;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SlackNotifierTest extends TestCase
{
    public function test_it_posts_to_slack_when_configured(): void
    {
        Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-fake-token');
        Config::set('services.slack.notifications.channel', '#alerts');

        Http::fake([
            'slack.com/*' => Http::response(['ok' => true], 200),
        ]);

        (new SlackNotifier)->send('Test message');

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://slack.com/api/chat.postMessage'
                && $request['channel'] === '#alerts'
                && $request['text'] === 'Test message'
                && $request->hasHeader('Authorization', 'Bearer xoxb-fake-token');
        });
    }

    public function test_it_does_nothing_when_not_configured(): void
    {
        Config::set('services.slack.notifications.bot_user_oauth_token', null);
        Config::set('services.slack.notifications.channel', null);

        Http::fake(function () {
            $this->fail('Slack should not be called when unconfigured.');
        });

        (new SlackNotifier)->send('Test message');

        $this->addToAssertionCount(1);
    }
}
