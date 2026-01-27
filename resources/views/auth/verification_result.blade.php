<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .card {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 400px;
            width: 100%;
        }

        .icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .success {
            color: #28a745;
        }

        .error {
            color: #dc3545;
        }

        .title {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #333;
        }

        .message {
            color: #666;
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.2s;
        }

        .btn:hover {
            background-color: #0056b3;
        }
    </style>
</head>

<body>
    <div class="card">
        @if($status == 'success')
            <div class="icon success">✅</div>
            <h1 class="title">Verified!</h1>
            <p class="message">{{ $message }}</p>
            <a href="{{ env('FRONT_END_URL') }}" class="btn">Go to Dashboard</a>
        @else
            <div class="icon error">❌</div>
            <h1 class="title">Verification Failed</h1>
            <p class="message">{{ $message }}</p>

            <div style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 20px;">
                <p style="font-size: 0.9rem; color: #555;">Need a new link?</p>
                <form action="{{ url('/resend-verification') }}" method="POST">
                    @csrf
                    <div style="margin-bottom: 10px;">
                        <input type="email" name="email" placeholder="Enter your email address" required
                            style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 80%;">
                    </div>
                    <button type="submit" class="btn" style="border: none; cursor: pointer;">Resend Verification
                        Email</button>
                </form>
            </div>

            <div style="margin-top: 15px;">
                <a href="{{ env('FRONT_END_URL') }}"
                    style="color: #007bff; text-decoration: none; font-size: 0.9rem;">Return Home</a>
            </div>
        @endif
    </div>
</body>

</html>
