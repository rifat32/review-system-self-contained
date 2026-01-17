<?php

namespace App\Services\User;

use App\Models\User;
use App\Mail\NotifyMail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UserService
{
    /**
     * Create a new business owner user
     */
    public function createBusinessOwner(array $data): User
    {
        $user = $this->createUser($data);

        $this->sendVerificationEmail($user, $data['email']);

        return $user;
    }

    /**
     * Create a new user with hashed password
     */
    private function createUser(array $data): User
    {
        return User::create([
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'first_Name' => $data['first_Name'],
            'last_Name' => $data['last_Name'],
            'phone' => $data['phone'] ?? null,
            'type' => 'business_Owner',
            'remember_token' => Str::random(10)
        ]);
    }

    /**
     * Send email verification if enabled in config
     */
    private function sendVerificationEmail(User $user, string $email): void
    {
        if (!config('app.send_email', false)) {
            return;
        }

        $user->update([
            'email_verify_token' => Str::random(30),
            'email_verify_token_expires' => now()->addDay()
        ]);

        Mail::to($email)->send(new NotifyMail($user));
    }




    /**
     * Verify user email
     */
    public function verifyEmail(User $user): bool
    {
        return $user->update([
            'email_verified_at' => now(),
            'email_verify_token' => null,
            'email_verify_token_expires' => null
        ]);
    }

    /**
     * Get user registration trends over time
     * 
     * @param mixed $users Users collection or query builder
     * @param string $period Period (7d, 30d, 90d, 1y)
     * @return array Time-series data with registration counts
     */
    public function getRegistrationTrends($users, string $period): array
    {
        $endDate = \Carbon\Carbon::now();
        $startDate = match ($period) {
            '7d' => \Carbon\Carbon::now()->subDays(7),
            '90d' => \Carbon\Carbon::now()->subDays(90),
            '1y' => \Carbon\Carbon::now()->subYear(),
            default => \Carbon\Carbon::now()->subDays(30)
        };

        $groupFormat = match ($period) {
            '7d' => 'd-m-Y',
            '90d', '1y' => 'm-Y',
            default => 'd-m-Y'
        };

        // GET DATA FROM QUERY BUILDER
        if ($users instanceof \Illuminate\Database\Eloquent\Builder) {
            $users = $users->get();
        }

        $usersArray = is_array($users) ? $users : $users->toArray();

        // FILTER USERS BY DATE RANGE
        $filteredUsers = [];
        foreach ($usersArray as $user) {
            $createdAt = is_array($user)
                ? ($user['created_at'] ?? null)
                : ($user->created_at ?? null);

            if (!$createdAt)
                continue;

            $userDate = \Carbon\Carbon::parse($createdAt);
            if ($userDate->between($startDate, $endDate)) {
                $filteredUsers[] = $user;
            }
        }

        // GROUP BY PERIOD
        $registrationsByPeriod = [];
        foreach ($filteredUsers as $user) {
            $createdAt = is_array($user)
                ? ($user['created_at'] ?? null)
                : ($user->created_at ?? null);

            if (!$createdAt)
                continue;

            $periodKey = \Carbon\Carbon::parse($createdAt)->format($groupFormat);

            if (!isset($registrationsByPeriod[$periodKey])) {
                $registrationsByPeriod[$periodKey] = 0;
            }

            $registrationsByPeriod[$periodKey]++;
        }

        // GENERATE ALL DATES IN RANGE WITH ZERO VALUES
        $allPeriods = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $periodKey = $current->format($groupFormat);

            if (!isset($allPeriods[$periodKey])) {
                $allPeriods[$periodKey] = 0;
            }

            // INCREMENT BASED ON PERIOD TYPE
            if ($groupFormat === 'd-m-Y') {
                $current->addDay();
            } else {
                $current->addMonth();
            }
        }

        // MERGE ACTUAL DATA WITH ALL PERIODS
        foreach ($registrationsByPeriod as $periodKey => $count) {
            $allPeriods[$periodKey] = $count;
        }

        // CONVERT TO ARRAY FORMAT
        $data = [];
        foreach ($allPeriods as $period => $count) {
            $data[] = [
                'period' => $period,
                'count' => $count
            ];
        }

        // SORT BY PERIOD (CHRONOLOGICALLY)
        usort($data, function ($a, $b) use ($groupFormat) {
            $dateA = \Carbon\Carbon::createFromFormat($groupFormat, $a['period']);
            $dateB = \Carbon\Carbon::createFromFormat($groupFormat, $b['period']);
            return $dateA <=> $dateB;
        });

        return $data;
    }
}
