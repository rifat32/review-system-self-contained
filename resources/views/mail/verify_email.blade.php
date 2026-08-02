<x-mail-layout 
    title="Verify Your Email Address" 
    :app-name="config('app.name', 'FeedGenius')" 
    security-notice="If you didn't create an account, you can safely ignore this email."
>
    <!-- Title -->
    <h2 style="margin: 0 0 16px 0; font-size: 26px; font-weight: 700; color: #f1f5f9; letter-spacing: -0.5px;">
        Welcome to {{ config('app.name', 'FeedGenius') }}!
    </h2>

    <!-- Greeting & Message -->
    <p style="margin: 0 0 24px 0; font-size: 16px; line-height: 1.625; color: #94a3b8;">
        Hi <strong style="color: #f1f5f9;">{{ $user->first_Name }} {{ $user->last_Name }}</strong>,<br><br>
        Thank you for registering. To get started, please click the button below to verify your email address.
    </p>

    <!-- Primary CTA Button (Emerald Green Gradient) -->
    <table border="0" cellpadding="0" cellspacing="0" style="margin: 0 auto 32px auto;">
        <tr>
            <td align="center" style="border-radius: 10px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); background-color: #10b981; box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);">
                <a href="{{ env('APP_URL') }}/activate/{{ $user->email_verify_token }}?email={{ urlencode($user->email) }}" target="_blank" style="display: inline-block; padding: 14px 36px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 10px; letter-spacing: 0.2px;">
                    Verify Email Address
                </a>
            </td>
        </tr>
    </table>

    <!-- Fallback Link Section -->
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-top: 1px solid #334155; padding-top: 24px;">
        <tr>
            <td align="left">
                <p style="margin: 0 0 10px 0; font-size: 13px; color: #94a3b8; line-height: 1.5;">
                    If the button above doesn't work, copy and paste the following link into your browser:
                </p>
                <div style="background-color: #0f172a; border: 1px solid #334155; border-radius: 8px; padding: 14px; word-break: break-all; font-size: 13px; line-height: 1.5;">
                    <a href="{{ env('APP_URL') }}/activate/{{ $user->email_verify_token }}?email={{ urlencode($user->email) }}" target="_blank" style="color: #34d399; text-decoration: underline; font-weight: 500;">
                        {{ env('APP_URL') }}/activate/{{ $user->email_verify_token }}?email={{ urlencode($user->email) }}
                    </a>
                </div>
            </td>
        </tr>
    </table>
</x-mail-layout>
