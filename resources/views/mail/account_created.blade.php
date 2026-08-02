<x-mail-layout 
    title="Welcome to {{ config('app.name') }}" 
    :app-name="config('app.name', 'FeedGenius')" 
    security-notice="If you did not create this account, no further action is required."
>
    <!-- Title -->
    <h2 style="margin: 0 0 16px 0; font-size: 26px; font-weight: 700; color: #f1f5f9; letter-spacing: -0.5px;">
        Welcome to {{ config('app.name') }}
    </h2>

    <!-- Subtitle / Greeting -->
    <p style="margin: 0 0 16px 0; font-size: 18px; font-weight: 500; color: #f1f5f9;">
        👋 Hello!
    </p>
    <p style="margin: 0 0 24px 0; font-size: 15px; line-height: 1.625; color: #94a3b8; max-width: 480px;">
        We're excited to have you join us. Empowering your business to listen, understand, and act on customer feedback instantly starts here.
    </p>

    <p style="margin: 0 0 12px 0; font-size: 15px; color: #94a3b8;">
        Your account has been successfully created for:
    </p>

    <!-- Email Box -->
    <div style="background-color: #0f172a; border: 1px solid #334155; border-radius: 8px; padding: 14px; margin-bottom: 24px; font-size: 15px; font-weight: 600; color: #34d399; text-align: center;">
        {{ $user_email }}
    </div>

    <p style="margin: 0 0 32px 0; font-size: 14px; line-height: 1.625; color: #94a3b8; max-width: 480px;">
        To get started and unlock all the powerful features of {{ config('app.name') }}, please verify your email address by clicking the button below:
    </p>

    <!-- Primary CTA Button (Emerald Green Gradient) -->
    <table border="0" cellpadding="0" cellspacing="0" style="margin: 0 auto 16px auto;">
        <tr>
            <td align="center" style="border-radius: 10px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); background-color: #10b981; box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);">
                <a href="{{ $verification_url }}" target="_blank" style="display: inline-block; padding: 14px 36px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 10px; letter-spacing: 0.2px;">
                    Verify Email Address
                </a>
            </td>
        </tr>
    </table>
</x-mail-layout>