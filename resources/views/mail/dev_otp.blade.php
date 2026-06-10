<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Developer Verification Code</title>
</head>
<body style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f4f5f6; margin: 0; padding: 0;">
    <table cellpadding="0" cellspacing="0" width="100%" style="background-color: #f4f5f6; padding: 20px;">
        <tr>
            <td>
                <table align="center" cellpadding="0" cellspacing="0" width="560" style="background-color: #ffffff; border: 1px solid #e9e9e9; border-radius: 8px; padding: 40px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);">
                    <tr>
                        <td style="text-align: center; padding-bottom: 20px;">
                            <h2 style="color: #6366f1; margin: 0; font-size: 24px;">Developer Access Control</h2>
                        </td>
                    </tr>
                    <tr>
                        <td style="color: #333333; font-size: 16px; line-height: 1.5; padding-bottom: 20px;">
                            Hello,
                            <br><br>
                            You have requested access to the Developer Tools & Utilities Dashboard. Please use the following one-time verification code (OTP) to authenticate:
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: center; padding: 20px 0;">
                            <div style="background-color: #f1f3f9; border-radius: 8px; display: inline-block; padding: 15px 30px; font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #1e293b; border: 1px solid #e2e8f0;">
                                {{ $otp }}
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="color: #666666; font-size: 14px; line-height: 1.5; padding-top: 20px; border-top: 1px solid #eeeeee;">
                            This code is valid for 10 minutes. If you did not request this access, please ignore this email.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
