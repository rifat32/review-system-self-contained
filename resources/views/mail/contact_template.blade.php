<!DOCTYPE html>
<html>

<head>
    <title>New Message Received</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }

        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #00C853;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }

        .header h1 {
            color: #333;
            margin: 0;
            font-size: 24px;
        }

        .content p {
            font-size: 16px;
            color: #555;
            line-height: 1.6;
        }

        .info-table {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }

        .label {
            font-weight: bold;
            color: #333;
            width: 120px;
        }

        .message-box {
            background-color: #f9f9f9;
            padding: 15px;
            border-left: 4px solid #00C853;
            border-radius: 4px;
        }

        .footer {
            text-align: center;
            font-size: 12px;
            color: #999;
            margin-top: 30px;
        }
    </style>
</head>

<body>

    <div class="email-container">
        <div class="header">
            <h1>New Website Inquiry</h1>
        </div>

        <div class="content">
            <p>Hello FeedGenius Team,</p>
            <p>You have received a new message from your website contact form. Here are the details:</p>

            <table class="info-table">
                <tr>
                    <td class="label">Name:</td>
                    <td>{{ $data['first_name'] }} {{ $data['last_name'] }}</td>
                </tr>
                <tr>
                    <td class="label">Email:</td>
                    <td><a href="mailto:{{ $data['email'] }}">{{ $data['email'] }}</a></td>
                </tr>
                <tr>
                    <td class="label">Subject:</td>
                    <td>{{ $data['subject'] }}</td>
                </tr>
            </table>

            <p class="label">Message:</p>
            <div class="message-box">
                {{ $data['message'] }}
            </div>
        </div>

        <div class="footer">
            <p>This email was sent automatically from your website contact form.</p>
        </div>
    </div>

</body>

</html>
