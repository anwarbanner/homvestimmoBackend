<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlackNotifier
{
    /**
     * Posts a message via Slack's Web API (chat.postMessage) using the Bot
     * User OAuth Token already scaffolded in config/services.php. Silently
     * does nothing if no token/channel is configured, so the app works fine
     * without Slack set up — this is a best-effort alert, not a core flow.
     */
    public function send(string $message): void
    {
        $token = config('services.slack.notifications.bot_user_oauth_token');
        $channel = config('services.slack.notifications.channel');

        if (empty($token) || empty($channel)) {
            return;
        }

        try {
            $response = Http::withToken($token)->post('https://slack.com/api/chat.postMessage', [
                'channel' => $channel,
                'text' => $message,
            ]);

            if (! $response->successful() || ! $response->json('ok')) {
                Log::warning('Échec d\'envoi de la notification Slack.', ['response' => $response->body()]);
            }
        } catch (\Throwable $e) {
            Log::warning('Échec d\'envoi de la notification Slack.', ['error' => $e->getMessage()]);
        }
    }
}
