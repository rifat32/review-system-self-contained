<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ config('app.name') }}</title>
    <style>
        /* Email client compatibility resets */
        body,
        table,
        td,
        a {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }

        table,
        td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }

        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }

        table {
            border-collapse: collapse !important;
        }

        body {
            height: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f3f4f6;
        }

        /* Mobile responsive styles */
        @media screen and (max-width: 600px) {
            .container {
                width: 100% !important;
                max-width: 100% !important;
            }

            .content-padding {
                padding: 20px !important;
            }
        }
    </style>
</head>

<body style="background-color: #f3f4f6; margin: 0; padding: 0;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <table border="0" cellpadding="0" cellspacing="0" width="450" class="container" style="background-color: #ffffff; border-radius: 24px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
                    <!-- Header Logo -->
                    <tr>
                        <td align="center" style="padding: 32px; background-color: #ffffff; border-bottom: 1px solid #f3f4f6;">
                            <span style="color: #074d61; font-weight: bold; font-size: 24px; letter-spacing: -0.025em;">feed<span style="color: #22c55e;">genius</span></span>
                        </td>
                    </tr>
                    <!-- Banner -->
                    <tr>
                        <td align="center" style="background-color: #074d61; padding: 40px 20px; position: relative;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: bold;">Welcome to {{ config('app.name') }}</h1>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td class="content-padding" style="padding: 32px; color: #4b5563;">
                            <p style="font-size: 18px; font-weight: 500; color: #1e293b; margin-top: 0; margin-bottom: 16px;">👋 Hello!</p>
                            <p style="line-height: 1.625; margin-bottom: 24px;">
                                We're excited to have you join us. Empowering your business to listen, understand, and act on customer feedback instantly starts here.
                            </p>
                            <p style="margin-bottom: 16px;"> Your account has been successfully created for: </p>
                            <div style="background-color: #f9fafb; padding: 16px; border-radius: 12px; border-left: 4px solid #22c55e; margin-bottom: 24px;">
                                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                    <tr>
                                        <td style="font-weight: 600; color: #074d61;">{{ $user_email }}</td>
                                    </tr>
                                </table>
                            </div>
                            <p style="font-size: 14px; line-height: 1.5; margin-bottom: 24px;">
                                To get started and unlock all the powerful features of {{ config('app.name') }}, please verify your email address by clicking the button below:
                            </p>
                            <div align="center" style="margin-bottom: 24px;">
                                <a href="{{ $verification_url }}" style="background-color: #22c55e; color: #ffffff; display: block; padding: 16px; border-radius: 12px; text-decoration: none; font-weight: bold; font-size: 16px; text-align: center; box-shadow: 0 10px 15px -3px rgba(34, 197, 94, 0.2);">
                                    Verify Email Address
                                </a>
                            </div>
                            <p style="font-size: 12px; color: #9ca3af; text-align: center; font-style: italic; margin-bottom: 0;">
                                If you did not create this account, no further action is required.
                            </p>
                        </td>
                    </tr>
                    <!-- Footer Info -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 32px; border-top: 1px solid #f3f4f6;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td width="50%" valign="top" style="padding-right: 10px;">
                                        <h4 style="color: #074d61; font-size: 12px; font-weight: bold; margin: 0 0 8px 0; text-transform: uppercase; letter-spacing: 0.05em;">Product</h4>
                                        <div style="color: #6b7280; font-size: 13px; line-height: 1.5;">
                                            How it Works<br>
                                            Solutions<br>
                                            Pricing
                                        </div>
                                    </td>
                                    <td width="50%" valign="top">
                                        <h4 style="color: #074d61; font-size: 12px; font-weight: bold; margin: 0 0 8px 0; text-transform: uppercase; letter-spacing: 0.05em;">Help</h4>
                                        <div style="color: #6b7280; font-size: 13px; line-height: 1.5;">
                                            Contact Support<br>
                                            Privacy Policy<br>
                                            Our FAQs
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2" align="center" style="padding-top: 32px; border-top: 1px solid #e5e7eb; margin-top: 24px;">
                                        <p style="font-size: 10px; color: #9ca3af; margin: 0 0 8px 0; text-transform: uppercase; letter-spacing: 0.1em;">
                                            Empowering businesses to listen, understand, and act on customer feedback instantly.
                                        </p>
                                        <p style="font-size: 10px; color: #d1d5db; margin: 0;">
                                            © {{ date('Y') }} {{ config('app.name') }} Inc. All rights reserved.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>