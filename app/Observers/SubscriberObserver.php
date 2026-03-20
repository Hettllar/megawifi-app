<?php

namespace App\Observers;

use App\Models\Subscriber;
use App\Models\SmsSettings;
use App\Services\SmsService;
use Exception;
use Illuminate\Support\Facades\Log;

class SubscriberObserver
{
    /**
     * Handle the Subscriber "created" event.
     * Send welcome SMS if enabled for the router.
     */
    public function created(Subscriber $subscriber): void
    {
        try {
            // Only send if subscriber has phone number
            if (empty($subscriber->phone)) {
                return;
            }

            // Check if welcome SMS is enabled for this router
            $settings = SmsSettings::where('router_id', $subscriber->router_id)->first();

            if (!$settings || !$settings->is_enabled || !$settings->welcome_enabled) {
                return;
            }

            // Send welcome SMS asynchronously via dispatch
            dispatch(function () use ($subscriber) {
                try {
                    $router = $subscriber->router;
                    if (!$router) return;

                    $smsService = new SmsService($router);
                    $log = $smsService->sendWelcomeSms($subscriber);
                    $smsService->disconnect();

                    if ($log && $log->status === 'sent') {
                        Log::info("Welcome SMS sent to {$subscriber->username} ({$subscriber->phone})");
                    }
                } catch (Exception $e) {
                    Log::error("Welcome SMS failed for {$subscriber->username}: " . $e->getMessage());
                }
            })->afterResponse();

        } catch (Exception $e) {
            Log::error("SubscriberObserver welcome SMS error: " . $e->getMessage());
        }
    }

    /**
     * Handle the Subscriber "updated" event.
     * Send disconnect SMS when subscriber status changes to 'disabled'.
     */
    public function updated(Subscriber $subscriber): void
    {
        try {
            // Only trigger when status changed TO 'disabled'
            if (!$subscriber->wasChanged('status') || $subscriber->status !== 'disabled') {
                return;
            }

            // Only send if subscriber has phone number
            if (empty($subscriber->phone)) {
                return;
            }

            // Check if disconnect SMS is enabled for this router
            $settings = SmsSettings::where('router_id', $subscriber->router_id)->first();
            if (!$settings || !$settings->is_enabled || !$settings->disconnect_enabled) {
                return;
            }

            // Send disconnect SMS asynchronously
            dispatch(function () use ($subscriber) {
                try {
                    $router = $subscriber->router;
                    if (!$router) return;

                    $settings = \App\Models\SmsSettings::where('router_id', $router->id)->first();
                    if (!$settings) return;

                    $message = $settings->parseDisconnectMessage($subscriber);

                    $smsService = new SmsService($router);
                    $phone = $subscriber->phone;
                    $phone = preg_replace('/[^0-9+]/', '', $phone);

                    if (strlen($phone) < 9) {
                        Log::warning("Disconnect SMS skipped for {$subscriber->username}: invalid phone {$phone}");
                        return;
                    }

                    $log = $smsService->sendSms($phone, $message, $subscriber->id);
                    $smsService->disconnect();

                    if ($log && $log->status === 'sent') {
                        Log::info("Disconnect SMS sent to {$subscriber->username} ({$subscriber->phone})");
                    }
                } catch (Exception $e) {
                    Log::error("Disconnect SMS failed for {$subscriber->username}: " . $e->getMessage());
                }
            })->afterResponse();

        } catch (Exception $e) {
            Log::error("SubscriberObserver disconnect SMS error: " . $e->getMessage());
        }
    }
}
