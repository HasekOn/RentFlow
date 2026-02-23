<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Rating;
use App\Models\User;

class TrustScoreService
{
    /**
     * Calculate and save trust score for a tenant
     * Score = (payment score × 0.6) + (rating score × 0.4)
     * Range: 0–100
     */
    public function calculate(User $user): float
    {
        $paymentScore = $this->calculatePaymentScore($user);
        $ratingScore = $this->calculateRatingScore($user);

        // If tenant has no ratings yet, use only payment score
        if ($ratingScore === null) {
            $totalScore = $paymentScore;
        } else {
            $totalScore = ($paymentScore * 0.6) + ($ratingScore * 0.4);
        }

        // Clamp to 0–100
        $totalScore = max(0, min(100, round($totalScore, 2)));

        // Save to user
        $user->update(['trust_score' => $totalScore]);

        return $totalScore;
    }

    /**
     * Payment morality score (0–100)
     * Based on how many days after due_date the payment was made
     *
     * On time or early: +10 points
     * 1–7 days late:    +5 points
     * 8–14 days late:    0 points
     * 15+ days late:    -10 points
     * Unpaid:           -10 points
     */
    private function calculatePaymentScore(User $user): float
    {
        $leaseIds = $user->leases()->pluck('id');

        $payments = Payment::whereIn('lease_id', $leaseIds)
            ->where('type', 'rent')
            ->get();

        if ($payments->isEmpty()) {
            return 50.0; // Default score for new tenants
        }

        $totalPoints = 0;
        $maxPoints = $payments->count() * 10; // Best case: all on time

        foreach ($payments as $payment) {
            if ($payment->status === 'unpaid' || $payment->paid_date === null) {
                $totalPoints -= 10;
                continue;
            }

            $daysLate = $payment->due_date->diffInDays($payment->paid_date, false);

            if ($daysLate <= 0) {
                // On time or early
                $totalPoints += 10;
            } elseif ($daysLate <= 7) {
                $totalPoints += 5;
            } elseif ($daysLate <= 14) {
                $totalPoints += 0;
            } else {
                $totalPoints -= 10;
            }
        }

        // Normalize to 0–100
        // maxPoints = best possible, minPoints = worst possible (-10 × count)
        $minPoints = $payments->count() * -10;
        $range = $maxPoints - $minPoints;

        if ($range === 0) {
            return 50.0;
        }

        return (($totalPoints - $minPoints) / $range) * 100;
    }

    /**
     * Rating score (0–100 or null if no ratings)
     * Average of all rating scores × 20
     * (score is 1–5, so max average 5 × 20 = 100)
     */
    private function calculateRatingScore(User $user): ?float
    {
        $leaseIds = $user->leases()->pluck('id');

        $averageScore = Rating::whereIn('lease_id', $leaseIds)->avg('score');

        if ($averageScore === null) {
            return null;
        }

        return $averageScore * 20;
    }
}
