<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SyncFailureNotifier
{
    public function notify(string $jobName, \Throwable $exception): void
    {
        $channels = config('integration.notify_on_failure', ['log']);
        $message = sprintf(
            'Integration job failed: %s. Error: %s',
            $jobName,
            $exception->getMessage()
        );

        if (in_array('log', $channels, true)) {
            Log::channel('integration')->error($message, [
                'job' => $jobName,
                'exception' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }

        if (in_array('mail', $channels, true)) {
            $this->sendMail($message, $exception);
        }

        if (in_array('slack', $channels, true)) {
            $this->sendSlack($message, $exception);
        }
    }

    protected function sendMail(string $message, \Throwable $exception): void
    {
        $to = config('integration.notification_email');
        if (empty($to)) {
            return;
        }
        try {
            Mail::raw($message . "\n\n" . $exception->getTraceAsString(), function ($m) use ($to) {
                $m->to($to)->subject('Opera ERP Integration – Job Failed');
            });
        } catch (\Throwable $e) {
            Log::channel('integration')->warning('Failed to send failure email', ['error' => $e->getMessage()]);
        }
    }

    protected function sendSlack(string $message, \Throwable $exception): void
    {
        $url = env('LOG_SLACK_WEBHOOK_URL');
        if (empty($url)) {
            return;
        }
        try {
            Http::post($url, [
                'text' => $message,
                'attachments' => [
                    ['text' => substr($exception->getTraceAsString(), 0, 2000), 'color' => 'danger'],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::channel('integration')->warning('Failed to send failure Slack notification', ['error' => $e->getMessage()]);
        }
    }
}
