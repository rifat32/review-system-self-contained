<?php

namespace App\Services;

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
}
