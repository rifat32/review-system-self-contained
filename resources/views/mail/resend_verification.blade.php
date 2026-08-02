<x-mail-layout 
    title="Verify Your Email Address" 
    :app-name="config('app.name', 'FeedGenius')" 
    security-notice="If you did not make this request, you can safely ignore this email."
>
    <!-- Title -->
    <h2 style="margin: 0 0 16px 0; font-size: 26px; font-weight: 700; color: #f1f5f9; letter-spacing: -0.5px;">
        Verify Your Email Address
    </h2>

    <p style="margin: 0 0 16px 0; font-size: 18px; font-weight: 500; color: #f1f5f9;">
        Hello,
    </p>

    <p style="margin: 0 0 12px 0; font-size: 15px; color: #94a3b8; line-height: 1.625;">
        We received a request to resend the verification email for your account associated with:
    </p>

    <!-- Email Box -->
    <div style="background-color: #0f172a; border: 1px solid #334155; border-radius: 8px; padding: 14px; margin-bottom: 24px; font-size: 15px; font-weight: 600; color: #34d399; text-align: center;">
        {{ $user_email }}
    </div>

    <p style="margin: 0 0 32px 0; font-size: 15px; color: #94a3b8; line-height: 1.625;">
        Please click the button below to verify your email address and complete your setup:
    </p>

    <!-- Primary CTA Button (Emerald Green Gradient) -->
    <table border="0" cellpadding="0" cellspacing="0" style="margin: 0 auto 32px auto;">
        <tr>
            <td align="center" style="border-radius: 10px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); background-color: #10b981; box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);">
                <a href="{{ $verification_url }}" target="_blank" style="display: inline-block; padding: 14px 36px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 10px; letter-spacing: 0.2px;">
                    Verify Email Address
                </a>
            </td>
        </tr>
    </table>

    <!-- Help Section -->
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-top: 1px solid #334155; padding-top: 24px;">
        <tr>
            <td align="left">
                <p style="margin: 0; font-size: 14px; color: #94a3b8; line-height: 1.5;">
                    <strong style="color: #f1f5f9;">Still stuck?</strong> We're here to help! 
                    <a href="{{ env('FRONT_END_URL', 'http://localhost:3000') }}/contact" style="color: #34d399; text-decoration: underline;">Contact Support</a>.
                </p>
            </td>
        </tr>
    </table>

    <!-- Fallback Link Section -->
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="padding-top: 16px;">
        <tr>
            <td align="left">
                <p style="margin: 0 0 10px 0; font-size: 13px; color: #94a3b8; line-height: 1.5;">
                    If you're having trouble clicking the "Verify Email Address" button, copy and paste the URL below into your web browser:
                </p>
                <div style="background-color: #0f172a; border: 1px solid #334155; border-radius: 8px; padding: 14px; word-break: break-all; font-size: 13px; line-height: 1.5;">
                    <a href="{{ $verification_url }}" target="_blank" style="color: #34d399; text-decoration: underline; font-weight: 500;">{{ $verification_url }}</a>
                </div>
            </td>
        </tr>
    </table>

</x-mail-layout>