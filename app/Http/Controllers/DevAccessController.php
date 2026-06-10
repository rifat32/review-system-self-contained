<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\DevOtpMail;
use Illuminate\Support\Str;

class DevAccessController extends Controller
{
    /**
     * Show the developer login page or the current step.
     */
    public function showLogin(Request $request)
    {
        if ($request->session()->get('developer_authenticated')) {
            return redirect('/');
        }
        return view('dev-login');
    }

    /**
     * Step 1: Verify developer password/key.
     */
    public function verifyPassword(Request $request)
    {
        $request->validate([
            'key' => 'required|string',
        ]);

        $accessKey = env('DEV_ACCESS_KEY');

        if (empty($accessKey)) {
            return back()->with('error', 'Developer access key is not configured in env.');
        }

        if ($request->input('key') !== $accessKey) {
            return back()->with('error', 'Invalid developer access password.');
        }

        $request->session()->put('dev_password_verified', true);

        return redirect()->route('dev.login');
    }

    /**
     * Step 2: Verify email and send OTP (with silent decoy).
     */
    public function sendOtp(Request $request)
    {
        // Must have verified password first
        if (!$request->session()->get('dev_password_verified')) {
            return redirect()->route('dev.login');
        }

        $request->validate([
            'email' => 'required|email',
        ]);

        $email = strtolower(trim($request->input('email')));
        $emailsString = env('DEV_ACCESS_EMAILS', '');
        $allowedEmails = array_filter(array_map('trim', explode(',', $emailsString)));
        $allowedEmails = array_map('strtolower', $allowedEmails);

        // Standard OTP session data
        $otp = sprintf("%06d", mt_rand(0, 999999));
        $expiresAt = now()->addMinutes(10)->timestamp;

        if (in_array($email, $allowedEmails)) {
            // Authorized email: Store OTP code and send email
            $request->session()->put([
                'dev_otp_email' => $email,
                'dev_otp_code' => $otp,
                'dev_otp_expires_at' => $expiresAt,
                'dev_otp_is_decoy' => false,
            ]);

            try {
                Mail::to($email)->send(new DevOtpMail($otp));
            } catch (\Exception $e) {
                return back()->with('error', 'Failed to send verification email. Please try again.');
            }
        } else {
            // Decoy flow for unauthorized email:
            // Store a dummy code that will never match the user input, or flag it as decoy.
            $request->session()->put([
                'dev_otp_email' => $email,
                'dev_otp_code' => 'DECOY_' . Str::random(10), // never matches a 6-digit number
                'dev_otp_expires_at' => $expiresAt,
                'dev_otp_is_decoy' => true,
            ]);
            // Do NOT send any email
        }

        return redirect()->route('dev.login')->with('success', 'A verification code has been sent to your email.');
    }

    /**
     * Step 3: Verify the entered OTP.
     */
    public function verifyOtp(Request $request)
    {
        // Must have verified password first
        if (!$request->session()->get('dev_password_verified')) {
            return redirect()->route('dev.login');
        }

        $request->validate([
            'otp' => 'required|string|size:6',
        ]);

        $sessionOtp = $request->session()->get('dev_otp_code');
        $sessionEmail = $request->session()->get('dev_otp_email');
        $expiresAt = $request->session()->get('dev_otp_expires_at');
        $isDecoy = $request->session()->get('dev_otp_is_decoy', false);

        if (!$sessionOtp || !$sessionEmail || !$expiresAt) {
            return redirect()->route('dev.login')->with('error', 'No verification session found. Please enter your email again.');
        }

        if (now()->timestamp > $expiresAt) {
            $this->clearOtpSession($request);
            return redirect()->route('dev.login')->with('error', 'Verification code has expired. Please request a new one.');
        }

        // Decoy check or wrong code
        if ($isDecoy || $request->input('otp') !== $sessionOtp) {
            return back()->with('error', 'Invalid verification code.');
        }

        // Authenticate developer
        $request->session()->put('developer_authenticated', true);

        // Clear all temporary access session data
        $request->session()->forget([
            'dev_password_verified',
            'dev_otp_email',
            'dev_otp_code',
            'dev_otp_expires_at',
            'dev_otp_is_decoy',
        ]);

        return redirect('/');
    }

    /**
     * Clear active OTP state to return to email stage.
     */
    public function clearOtp(Request $request)
    {
        $this->clearOtpSession($request);
        return redirect()->route('dev.login');
    }

    /**
     * Clear all state (including password) to return to password stage.
     */
    public function clearPassword(Request $request)
    {
        $request->session()->forget([
            'dev_password_verified',
            'dev_otp_email',
            'dev_otp_code',
            'dev_otp_expires_at',
            'dev_otp_is_decoy',
        ]);
        return redirect()->route('dev.login');
    }

    /**
     * Helper to clear temp OTP session variables.
     */
    private function clearOtpSession(Request $request)
    {
        $request->session()->forget([
            'dev_otp_email',
            'dev_otp_code',
            'dev_otp_expires_at',
            'dev_otp_is_decoy',
        ]);
    }
}
