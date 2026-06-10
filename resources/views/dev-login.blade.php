<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Developer Verification</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0b0f19;
            --bg-card: #151c2c;
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --border: #223049;
            --error: #ef4444;
            --success: #10b981;
        }
        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            overflow: hidden;
            position: relative;
        }
        /* Gradient backdrop */
        body::before {
            content: '';
            position: absolute;
            top: -20%;
            left: -20%;
            width: 140%;
            height: 140%;
            background: radial-gradient(circle at 50% 50%, rgba(99, 102, 241, 0.12) 0%, transparent 60%),
                        radial-gradient(circle at 20% 80%, rgba(16, 185, 129, 0.08) 0%, transparent 50%);
            z-index: -1;
        }
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 1.25rem;
            padding: 2.5rem;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 420px;
            text-align: center;
            backdrop-filter: blur(8px);
            animation: cardFadeIn 0.5s ease-out;
        }
        @keyframes cardFadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .logo {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0 0 0.75rem 0;
            background: linear-gradient(to right, #a5b4fc, #6366f1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        p {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.6;
            margin: 0 0 2rem 0;
        }
        .form-group {
            margin-bottom: 1.25rem;
            text-align: left;
        }
        label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--text-secondary);
        }
        input {
            width: 100%;
            padding: 0.875rem 1rem;
            border-radius: 0.75rem;
            border: 1px solid var(--border);
            background: rgba(11, 15, 25, 0.8);
            color: var(--text-primary);
            font-family: inherit;
            font-size: 1rem;
            box-sizing: border-box;
            transition: all 0.25s ease;
        }
        input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
            background: rgba(11, 15, 25, 0.95);
        }
        button {
            width: 100%;
            padding: 0.875rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 0.75rem;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.25s ease;
            margin-top: 0.5rem;
        }
        button:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 8px 20px -6px rgba(99, 102, 241, 0.5);
        }
        button:active {
            transform: translateY(0);
        }
        .alert {
            border-radius: 0.75rem;
            padding: 0.875rem 1rem;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            text-align: left;
            line-height: 1.5;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #fca5a5;
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: #a7f3d0;
        }
        .back-link {
            display: inline-block;
            margin-top: 1.5rem;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: color 0.2s ease;
        }
        .back-link:hover {
            color: var(--primary);
        }
    </style>
</head>
<body>
    <div class="card">
        @if(!session('dev_password_verified'))
            <!-- Stage 1: Password Verification -->
            <div class="logo">🛡️</div>
            <h1>Developer Access</h1>
            <p>Please enter the developer password to begin verification.</p>

            @if(session('error'))
                <div class="alert alert-error">{{ session('error') }}</div>
            @endif

            <form action="{{ route('dev.verify_password') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label for="key">Developer Password</label>
                    <input type="password" id="key" name="key" placeholder="Enter password" required autofocus>
                </div>
                <button type="submit">Verify Password</button>
            </form>

        @elseif(!session('dev_otp_email'))
            <!-- Stage 2: Email Verification -->
            <div class="logo">✉️</div>
            <h1>Developer Email</h1>
            <p>Enter your authorized developer email to receive a verification code.</p>

            @if(session('error'))
                <div class="alert alert-error">{{ session('error') }}</div>
            @endif
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <form action="{{ route('dev.send_otp') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label for="email">Developer Email</label>
                    <input type="email" id="email" name="email" placeholder="name@domain.com" required autofocus>
                </div>
                <button type="submit">Send Code</button>
            </form>
            <a href="{{ route('dev.clear_password') }}" class="back-link">← Back to password screen</a>

        @else
            <!-- Stage 3: OTP Verification -->
            <div class="logo">🔑</div>
            <h1>Verify OTP</h1>
            <p>A 6-digit verification code has been sent to <strong>{{ session('dev_otp_email') }}</strong>. Please enter it to unlock the dashboard.</p>

            @if(session('error'))
                <div class="alert alert-error">{{ session('error') }}</div>
            @endif
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <form action="{{ route('dev.verify_otp') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label for="otp">Verification Code</label>
                    <input type="text" id="otp" name="otp" placeholder="123456" pattern="[0-9]{6}" maxlength="6" required autofocus autocomplete="one-time-code">
                </div>
                <button type="submit">Verify & Unlock</button>
            </form>
            <a href="{{ route('dev.clear_otp') }}" class="back-link">← Use a different email</a>
        @endif
    </div>
</body>
</html>
