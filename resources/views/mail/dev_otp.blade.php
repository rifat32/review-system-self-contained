<x-mail-layout 
    title="Developer Verification Code" 
    :app-name="config('app.name', 'FeedGenius')" 
    security-notice="This code is valid for 10 minutes. If you did not request this access, please ignore this email."
>
    <!-- Title -->
    <h2 style="margin: 0 0 16px 0; font-size: 26px; font-weight: 700; color: #f1f5f9; letter-spacing: -0.5px;">
        Developer Access Control
    </h2>

    <p style="margin: 0 0 24px 0; font-size: 15px; color: #94a3b8; line-height: 1.625;">
        Hello,
        <br><br>
        You have requested access to the Developer Tools & Utilities Dashboard. Please use the following one-time verification code (OTP) to authenticate:
    </p>

    <!-- OTP Box -->
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 32px;">
        <tr>
            <td align="center">
                <div style="background-color: #0f172a; border-radius: 8px; display: inline-block; padding: 15px 30px; font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #34d399; border: 1px solid #334155;">
                    {{ $otp }}
                </div>
            </td>
        </tr>
    </table>

</x-mail-layout>
