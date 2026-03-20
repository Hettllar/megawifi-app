<?php

namespace App\Jobs;

use App\Models\Subscriber;
use App\Services\UserManagerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CheckExpiredSubscriptions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Mark as unpaid 3 days before expiry
        $this->markUnpaidBeforeExpiry();
        
        // Check UserManager expired subscriptions
        $this->handleUserManagerExpired();
        
        // Check PPPoE (Broadband) expired subscriptions
        $this->handlePPPoEExpired();
    }

    /**
     * Mark subscriptions as unpaid 3 days before expiry
     * This helps with payment reminders
     */
    protected function markUnpaidBeforeExpiry(): void
    {
        // Find subscriptions expiring in 3 days or less that are still marked as paid
        $expiringIn3Days = Subscriber::where('status', 'active')
            ->where('is_paid', true)
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '<=', Carbon::now()->addDays(3))
            ->where('expiration_date', '>', Carbon::now()) // Not yet expired
            ->get();

        $updatedCount = 0;

        foreach ($expiringIn3Days as $subscriber) {
            try {
                $subscriber->update([
                    'is_paid' => false,
                ]);
                $updatedCount++;
                Log::info("Marked as unpaid (3 days before expiry): {$subscriber->username}");
            } catch (\Exception $e) {
                Log::error("Error marking subscriber {$subscriber->username} as unpaid: " . $e->getMessage());
            }
        }

        if ($updatedCount > 0) {
            Log::info("Marked {$updatedCount} subscribers as unpaid (expiring within 3 days)");
        }
    }

    /**
     * Handle expired UserManager subscriptions
     * Disable user directly on router when subscription is expired
     */
    protected function handleUserManagerExpired(): void
    {
        $expired = Subscriber::where('status', 'active')
            ->where('type', 'usermanager')
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '<', Carbon::now())
            ->with('router')
            ->get();

        $successCount = 0;
        $failedCount = 0;

        foreach ($expired as $subscriber) {
            try {
                $subscriber->update([
                    'status' => 'expired',
                    'is_throttled' => false,
                    'throttled_at' => null,
                    'stopped_at' => now(),
                    'stop_reason' => 'subscription_expired',
                ]);

                if ($subscriber->router && $subscriber->mikrotik_id) {
                    try {
                        $service = new UserManagerService($subscriber->router);

                        if (!$service->connect()) {
                            throw new \Exception('Could not connect to router');
                        }

                        $result = $service->toggleUserStatus($subscriber->mikrotik_id, true);
                        $service->disconnect();

                        if (isset($result[0]['message'])) {
                            throw new \Exception($result[0]['message']);
                        }

                        Log::info("UserManager subscription expired and disabled: {$subscriber->username} (Router: {$subscriber->router->name})");
                        $successCount++;
                    } catch (\Exception $e) {
                        Log::error("Error disabling expired UserManager user {$subscriber->username}: " . $e->getMessage());
                        $failedCount++;
                    }
                } else {
                    Log::warning("Expired subscription but no router/mikrotik_id: {$subscriber->username}");
                    $failedCount++;
                }
            } catch (\Exception $e) {
                Log::error("Error processing expired subscription {$subscriber->username}: " . $e->getMessage());
                $failedCount++;
            }
        }

        if ($expired->count() > 0) {
            Log::info("UserManager: Checked expired subscriptions: {$expired->count()} total, {$successCount} disabled, {$failedCount} failed");
        }
    }

    /**
     * Handle expired PPPoE (Broadband) subscriptions
     * Simple: just disable the user, no data limits tracking
     */
    protected function handlePPPoEExpired(): void
    {
        $expired = Subscriber::where('status', 'active')
            ->where('type', 'ppp')
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '<', Carbon::now())
            ->with('router')
            ->get();

        $successCount = 0;
        $failedCount = 0;

        foreach ($expired as $subscriber) {
            try {
                // Update status to expired
                $subscriber->update([
                    'status' => 'expired',
                ]);

                // Disable PPP secret on router
                if ($subscriber->router && $subscriber->mikrotik_id) {
                    try {
                        $service = new \App\Services\MikroTikService($subscriber->router);
                        if ($service->connect()) {
                            // Disable the PPP secret
                            $service->updatePPPSecret($subscriber->mikrotik_id, ['disabled' => true]);
                            
                            Log::info("PPPoE subscription expired and disabled: {$subscriber->username} (Router: {$subscriber->router->name})");
                            $successCount++;
                        } else {
                            Log::warning("Failed to connect to router for PPPoE user: {$subscriber->username}");
                            $failedCount++;
                        }
                    } catch (\Exception $e) {
                        Log::error("Error disabling expired PPPoE user {$subscriber->username}: " . $e->getMessage());
                        $failedCount++;
                    }
                } else {
                    Log::warning("Expired PPPoE subscription but no router/mikrotik_id: {$subscriber->username}");
                    $failedCount++;
                }
            } catch (\Exception $e) {
                Log::error("Error processing expired PPPoE subscription {$subscriber->username}: " . $e->getMessage());
                $failedCount++;
            }
        }

        if ($expired->count() > 0) {
            Log::info("PPPoE: Checked expired subscriptions: {$expired->count()} total, {$successCount} disabled, {$failedCount} failed");
        }
    }

    /**
     * Handle expired Hotspot cards
     * Disable hotspot user on router when expiration_date has passed
     */
    protected function handleHotspotExpired(): void
    {
        $expired = Subscriber::where('status', 'active')
            ->where('type', 'hotspot')
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '<', Carbon::now())
            ->with('router')
            ->get();

        $successCount = 0;
        $failedCount = 0;

        foreach ($expired as $subscriber) {
            try {
                $subscriber->update([
                    'status' => 'expired',
                ]);

                if ($subscriber->router) {
                    try {
                        $service = new \App\Services\MikroTikService($subscriber->router);
                        $service->connect();

                        // Find user by username
                        $users = $service->getHotspotUsers();
                        $realId = null;
                        foreach ($users as $u) {
                            if (isset($u['name']) && $u['name'] === $subscriber->username) {
                                $realId = $u['.id'] ?? null;
                                break;
                            }
                        }

                        if ($realId) {
                            $service->command(['/ip/hotspot/user/set', '=.id=' . $realId, '=disabled=yes']);
                            Log::info("Hotspot card expired and disabled: {$subscriber->username} (Router: {$subscriber->router->name})");
                        } else {
                            Log::warning("Expired hotspot card not found on router: {$subscriber->username}");
                        }

                        $service->disconnect();
                        $successCount++;
                    } catch (\Exception $e) {
                        Log::error("Error disabling expired hotspot card {$subscriber->username}: " . $e->getMessage());
                        $failedCount++;
                    }
                } else {
                    $failedCount++;
                }
            } catch (\Exception $e) {
                Log::error("Error processing expired hotspot card {$subscriber->username}: " . $e->getMessage());
                $failedCount++;
            }
        }

        if ($expired->count() > 0) {
            Log::info("Hotspot: Checked expired cards: {$expired->count()} total, {$successCount} disabled, {$failedCount} failed");
        }
    }
}
