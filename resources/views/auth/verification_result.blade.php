<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - {{ config('app.name') }}</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #32CD32;
            --secondary: #004060;
            --base-100: #ffffff;
            --base-200: #f1f5f9;
            --base-content: #1e293b;
            --error: #ef4444;
            --success: #22c55e;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            margin: 0;
            padding: 0;
            color: var(--base-content);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .container {
            width: 100%;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .card {
            background-color: var(--base-100);
            padding: 3rem;
            border-radius: 1.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
            max-width: 42rem;
            width: 100%;
            text-align: center;
            border: 1px solid var(--base-200);
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .illustration {
            margin-bottom: 2rem;
            position: relative;
        }

        .icon-wrapper {
            width: 8rem;
            height: 8rem;
            margin: 0 auto;
            background-color: rgba(50, 205, 50, 0.1);
            /* Primary/10 */
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 10;
        }

        .icon-wrapper i {
            font-size: 3.5rem;
            color: var(--primary);
            filter: drop-shadow(0 4px 3px rgba(0, 0, 0, 0.07));
        }

        .icon-wrapper.error-icon {
            background-color: rgba(239, 68, 68, 0.1);
        }

        .icon-wrapper.error-icon i {
            color: var(--error);
        }

        .blob {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 12rem;
            height: 12rem;
            background-color: rgba(50, 205, 50, 0.05);
            border-radius: 50%;
            filter: blur(24px);
            z-index: 0;
        }

        .title {
            font-size: 1.875rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--secondary);
        }

        .message {
            font-size: 1.125rem;
            color: #64748b;
            margin-bottom: 2rem;
            line-height: 1.625;
            max-width: 28rem;
            margin-left: auto;
            margin-right: auto;
        }

        .message .email {
            font-weight: 700;
            color: var(--base-content);
        }

        .resend-box {
            background-color: var(--secondary);
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            color: #d1d5db;
        }

        .resend-box p {
            margin: 0;
            font-weight: 500;
        }

        .resend-btn {
            background: none;
            border: none;
            color: var(--primary);
            font-weight: 700;
            padding: 0;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }

        .resend-btn:hover {
            text-decoration: underline;
        }

        .action-btn {
            display: inline-block;
            background-color: var(--primary);
            color: white !important;
            padding: 0.875rem 2rem;
            border-radius: 0.5rem;
            text-decoration: none !important;
            font-weight: 700;
            font-size: 1rem;
            transition: all 0.2s;
            margin-bottom: 1rem;
        }

        .action-btn:hover {
            opacity: 0.9;
            transform: scale(1.02);
        }

        .help-text {
            font-size: 0.875rem;
            color: #94a3b8;
        }

        .help-text a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .help-text a:hover {
            text-decoration: underline;
        }

        .resend-form {
            display: none;
            /* Hide real form, trigger via JS */
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="card">
            <!-- Illustration Area -->
            <div class="illustration">
                <div class="icon-wrapper {{ $status == 'success' ? '' : 'error-icon' }}">
                    @if($status == 'success')
                        <i class="fa-solid fa-envelope-open-text"></i>
                    @else
                        <i class="fa-solid fa-circle-xmark"></i>
                    @endif
                </div>
                <div class="blob"></div>
            </div>

            @if($status == 'success')
                <h2 class="title">{{ $page_title }}</h2>
                <p class="message">
                    {{ $message }}
                </p>
                @if($status == 'success' && $page_title == 'Email Verified Successfully!')
                    <div class="btn-container">
                        <a href="{{ env('FRONT_END_DASHBOARD_URL') }}" class="action-btn">Go to Dashboard</a>
                    </div>
                @endif
            @else
                <h2 class="title">{{ $page_title }}</h2>
                <p class="message">
                    {{ $message }}
                </p>

                <div class="resend-box">
                    <p>
                        If you don't see the email, check your spam folder or
                    <form action="{{ url('/resend-verification') }}" method="POST" id="resend-form" style="display:inline;">
                        @csrf
                        <input type="hidden" name="email" value="{{ request()->query('email') }}">
                        <button type="submit" class="resend-btn">resend the email</button>
                    </form>
                    </p>
                </div>
            @endif

            <p class="help-text">
                Still stuck?
                <a href="{{ env('FRONT_END_URL') }}/contact" target="_blank" rel="noreferrer">
                    Contact Support
                </a>
                is here to help.
            </p>
        </div>
    </div>
</body>

</html>
