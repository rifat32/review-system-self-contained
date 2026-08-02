<x-mail-layout 
    title="Reset Your Password" 
    :app-name="$app_name ?? null" 
    :logo-url="$logo_url ?? null"
    security-notice="If you didn't request a password reset, you can safely ignore this email. Your password will remain unchanged."
>
    <!-- Title -->
    <h2 style="margin: 0 0 16px 0; font-size: 26px; font-weight: 700; color: #f1f5f9; letter-spacing: -0.5px;">
        Reset Your Password
    </h2>

    <!-- Subtitle / Greeting -->
    <p style="margin: 0 0 32px 0; font-size: 15px; line-height: 1.625; color: #94a3b8; max-width: 480px;">
        Hi <strong style="color: #f1f5f9;">{{ $user_name ?? 'User' }}</strong>, we received a request to reset the password for your <strong style="color: #f1f5f9;">{{ $app_name ?? 'FeedGenius' }}</strong> account. If you made this request, please click the button below to set a new password.
    </p>

    <!-- Primary CTA Button (Emerald Green Gradient) -->
    <table border="0" cellpadding="0" cellspacing="0" style="margin: 0 auto 32px auto;">
        <tr>
            <td align="center" style="border-radius: 10px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); background-color: #10b981; box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);">
                <a href="{{ $reset_url }}" target="_blank" style="display: inline-block; padding: 14px 36px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 10px; letter-spacing: 0.2px;">
                    Reset Password
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
                    <a href="{{ $reset_url }}" target="_blank" style="color: #34d399; text-decoration: underline; font-weight: 500;">{{ $reset_url }}</a>
                </div>
            </td>
        </tr>
    </table>
</x-mail-layout>
