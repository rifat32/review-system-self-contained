<x-mail-layout 
    title="Welcome to {{ $businessName }}!" 
    :app-name="$businessName" 
    security-notice="This is an automated email. Please do not reply to this message."
>
    <!-- Title -->
    <h2 style="margin: 0 0 16px 0; font-size: 26px; font-weight: 700; color: #f1f5f9; letter-spacing: -0.5px;">
        Welcome to {{ $businessName }}!
    </h2>

    <p style="margin: 0 0 16px 0; font-size: 18px; font-weight: 500; color: #f1f5f9;">
        Hello {{ $userName }},
    </p>

    <p style="margin: 0 0 24px 0; font-size: 15px; color: #94a3b8; line-height: 1.625;">
        Welcome! Your account as a <strong style="color: #f1f5f9;">{{ $role }}</strong> has been successfully created.
    </p>

    <!-- Credentials Box -->
    <div style="background-color: #0f172a; border-left: 4px solid #3b82f6; border-radius: 8px; padding: 24px; margin-bottom: 24px;">
        <h3 style="margin: 0 0 16px 0; font-size: 16px; color: #60a5fa;">🔐 Your Login Credentials</h3>
        
        <div style="margin-bottom: 12px; padding: 12px; background-color: #1e293b; border-radius: 6px; border: 1px solid #334155;">
            <div style="font-weight: 600; color: #94a3b8; font-size: 12px; text-transform: uppercase;">Email Address</div>
            <div style="color: #f1f5f9; font-size: 16px; margin-top: 4px; word-break: break-all;">{{ $email }}</div>
        </div>
        
        <div style="margin-bottom: 12px; padding: 12px; background-color: #1e293b; border-radius: 6px; border: 1px solid #334155;">
            <div style="font-weight: 600; color: #94a3b8; font-size: 12px; text-transform: uppercase;">Temporary Password</div>
            <div style="color: #f1f5f9; font-size: 16px; margin-top: 4px; word-break: break-all;">{{ $password }}</div>
        </div>

        <div style="padding: 12px; background-color: #1e293b; border-radius: 6px; border: 1px solid #334155;">
            <div style="font-weight: 600; color: #94a3b8; font-size: 12px; text-transform: uppercase;">Role</div>
            <div style="color: #f1f5f9; font-size: 16px; margin-top: 4px; word-break: break-all;">{{ $role }}</div>
        </div>
    </div>

    <!-- Warning -->
    <div style="background-color: rgba(245, 158, 11, 0.1); border-left: 4px solid #f59e0b; padding: 16px; margin-bottom: 24px; border-radius: 4px; color: #fcd34d; font-size: 14px; line-height: 1.5;">
        <strong style="color: #fbbf24;">⚠️ Security Notice:</strong> Please change your password after logging in for the first time. Keep your credentials secure and do not share them with anyone.
    </div>

    <!-- Primary CTA Button (Emerald Green Gradient) -->
    <table border="0" cellpadding="0" cellspacing="0" style="margin: 0 auto 32px auto;">
        <tr>
            <td align="center" style="border-radius: 10px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); background-color: #10b981; box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);">
                <a href="{{ $loginUrl }}" target="_blank" style="display: inline-block; padding: 14px 36px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 10px; letter-spacing: 0.2px;">
                    Login to Your Account
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 0 0 24px 0; font-size: 15px; color: #94a3b8; line-height: 1.625;">
        If you have any questions or need assistance, please don't hesitate to contact your administrator.
    </p>
    <p style="margin: 0; font-size: 15px; color: #94a3b8; line-height: 1.625;">
        Best regards,<br>
        <strong style="color: #f1f5f9;">{{ $businessName }} Team</strong>
    </p>

</x-mail-layout>
