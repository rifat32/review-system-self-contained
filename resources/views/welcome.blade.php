<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Review System - Developer Tools</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Centralized CSS Design System -->
    <link rel="stylesheet" href="{{ asset('css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('css/common.css') }}">

    <style>
        /* ==================== PAGE-SPECIFIC STYLES ==================== */

        /* Animated background gradient */
        body::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 50% 50%, rgba(99, 102, 241, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(139, 92, 246, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 20% 80%, rgba(16, 185, 129, 0.1) 0%, transparent 50%);
            animation: backgroundPulse 20s ease infinite;
            z-index: -1;
        }

        @keyframes backgroundPulse {

            0%,
            100% {
                transform: translate(0, 0) rotate(0deg);
            }

            33% {
                transform: translate(5%, 5%) rotate(120deg);
            }

            66% {
                transform: translate(-5%, 5%) rotate(240deg);
            }
        }

        /* Header specific styles */
        .header {
            text-align: center;
            margin-bottom: var(--spacing-xl);
            animation: fadeInDown 0.6s ease;
        }

        .header-title {
            font-size: 3rem;
            font-weight: 700;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: var(--spacing-sm);
            letter-spacing: -0.02em;
        }

        .header-subtitle {
            font-size: 1.125rem;
            color: var(--text-secondary);
            font-weight: 400;
        }

        /* ==================== GRID ==================== */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
        }

        /* ==================== CARDS ==================== */
        .card {
            background: var(--bg-card);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: var(--spacing-md);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease;
            animation-fill-mode: both;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, transparent 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .card:hover::before {
            opacity: 1;
        }

        .card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
            border-color: rgba(99, 102, 241, 0.3);
        }

        /* Stagger animations for cards */
        .card:nth-child(1) {
            animation-delay: 0.1s;
            animation: fadeInUp 0.6s ease both;
        }

        .card:nth-child(2) {
            animation-delay: 0.2s;
            animation: fadeInUp 0.6s ease both;
        }

        .card:nth-child(3) {
            animation-delay: 0.3s;
            animation: fadeInUp 0.6s ease both;
        }

        .card:nth-child(4) {
            animation-delay: 0.4s;
            animation: fadeInUp 0.6s ease both;
        }

        .card:nth-child(5) {
            animation-delay: 0.5s;
            animation: fadeInUp 0.6s ease both;
        }

        /* Page-specific grid */
        .grid {
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        }

        /* Button full width for this page */
        .btn {
            width: 100%;
        }

        /* Icon styles specific to this page */
        .card-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-sm);
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .icon-primary {
            background: rgba(99, 102, 241, 0.2);
            color: var(--primary-lighter);
        }

        .icon-secondary {
            background: rgba(16, 185, 129, 0.2);
            color: var(--secondary-lighter);
        }

        .icon-danger {
            background: rgba(239, 68, 68, 0.2);
            color: var(--danger-lighter);
        }

        .card-title {
            font-size: var(--text-lg);
            font-weight: var(--font-semibold);
            color: var(--text-primary);
        }

        .card-description {
            font-size: var(--text-sm);
            color: var(--text-secondary);
            margin-bottom: var(--spacing-md);
            line-height: var(--leading-normal);
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: var(--text-xs);
            font-weight: var(--font-semibold);
            background: rgba(16, 185, 129, 0.2);
            color: var(--secondary-lighter);
            margin-top: var(--spacing-sm);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .header-title {
                font-size: var(--text-4xl);
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <h1 class="header-title">Review System</h1>
            <p class="header-subtitle">Developer Tools & Utilities Dashboard</p>
        </header>

        <!-- Grid -->
        <div class="grid">
            <!-- Test API Card -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon icon-primary">
                        🧪
                    </div>
                    <h2 class="card-title">Test API</h2>
                </div>
                <p class="card-description">
                    Test and validate API endpoints in a custom testing environment
                </p>
                <a href="{{ env('APP_URL') }}/custom-test-api" class="btn btn-primary" target="_blank">
                    <span class="btn-icon">▶</span>
                    <span>Launch Tester</span>
                </a>
            </div>

            <!-- Swagger Refresh Card -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon icon-primary">
                        🔄
                    </div>
                    <h2 class="card-title">Swagger Refresh</h2>
                </div>
                <p class="card-description">
                    Regenerate API documentation from code annotations
                </p>
                <a href="{{ env('APP_URL') }}/swagger-refresh" class="btn btn-primary" target="_blank">
                    <span class="btn-icon">↻</span>
                    <span>Refresh Docs</span>
                </a>
            </div>

            <!-- Run Artisan Card -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon icon-primary">
                        💻
                    </div>
                    <h2 class="card-title">Run Artisan</h2>
                </div>
                <p class="card-description">
                    Select and run essential schedule and maintenance commands. Destructive commands are disabled.
                </p>
                <form method="get" action="{{ url('/run-artisan') }}" target="_blank"
                    onsubmit="return confirm('Run this artisan command?');">
                    <div style="display:flex;gap:8px;align-items:center;">
                        <select name="command" required
                            style="flex:1;padding:8px;border-radius:6px;border:1px solid var(--border);position:relative;z-index:2;pointer-events:auto;background:var(--bg-card);color:var(--text-primary);">
                            <optgroup label="Schedule Commands">
                                <option value="schedule:list">schedule:list</option>
                                <option value="schedule:run">schedule:run</option>
                            </optgroup>
                            <optgroup label="Project Commands">
                                <option value="recommendations:cleanup">recommendations:cleanup</option>
                                <option value="recommendations:generate">recommendations:generate</option>
                                <option value="rules:execute-scheduled">rules:execute-scheduled</option>
                                <option value="rules:regenerate-explanations">rules:regenerate-explanations</option>
                                <option value="reviews:process">reviews:process</option>
                                <option value="user_review_report:generate">user_review_report:generate</option>
                                <option value="guest_user_review_report:generate">guest_user_review_report:generate
                                </option>
                                <option value="businesses:purge-deleted">businesses:purge-deleted</option>
                            </optgroup>
                            <optgroup label="Maintenance">
                                <option value="optimize:clear">optimize:clear</option>
                                <option value="config:clear">config:clear</option>
                                <option value="cache:clear">cache:clear</option>
                                <option value="route:clear">route:clear</option>
                                <option value="view:clear">view:clear</option>
                                <option value="check:migrate">check:migrate</option>
                                <option value="l5-swagger:generate">l5-swagger:generate</option>
                            </optgroup>
                        </select>
                        <button class="btn btn-primary btn-sm" type="submit"
                            style="min-width:120px;padding:8px 12px;border-radius:6px;">Run</button>
                    </div>
                </form>
            </div>

            <!-- API Documentation Card -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon icon-secondary">
                        📚
                    </div>
                    <h2 class="card-title">API Documentation</h2>
                </div>
                <p class="card-description">
                    Interactive Swagger UI for exploring and testing API endpoints
                </p>
                <a href="{{ env('APP_URL') }}/api/documentation#/" class="btn btn-secondary" target="_blank">
                    <span class="btn-icon">📖</span>
                    <span>View Docs</span>
                </a>
            </div>

            <!-- Seed Demo Business Card -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon icon-primary">
                        🌱
                    </div>
                    <h2 class="card-title">Seed Demo Business</h2>
                </div>
                <p class="card-description">
                    Create a full demo business and 300 sample reviews. Provide an email to use for the owner.
                </p>
                <form method="get" action="{{ url('/run-demo-seeder') }}" target="_blank"
                    onsubmit="return confirm('Run demo seeder? This will create demo data.');">
                    <div style="display:flex;gap:8px;align-items:center;">
                        <input name="email" type="email" placeholder="owner@example.com" tabindex="1" autofocus
                            style="flex:1;padding:8px;border-radius:6px;border:1px solid var(--border);position:relative;z-index:2;pointer-events:auto;" />
                        <button class="btn btn-primary btn-sm" type="submit"
                            style="min-width:120px;padding:8px 12px;border-radius:6px;">Run Seeder</button>
                    </div>
                </form>
                <p class="card-description" style="margin-top:8px;font-size:12px;color:var(--text-secondary);">Only
                    allowed when running in local or debug mode.</p>
            </div>

            <!-- Activity Log Card -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon icon-secondary">
                        📊
                    </div>
                    <h2 class="card-title">Activity Log</h2>
                </div>
                <p class="card-description">
                    Monitor system activities, errors, and user actions
                </p>
                <a href="{{ env('APP_URL') }}/activity-log" class="btn btn-secondary" target="_blank">
                    <span class="btn-icon">👁</span>
                    <span>View Logs</span>
                </a>
            </div>

            <!-- Database Migration Card -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon icon-danger">
                        ⚡
                    </div>
                    <h2 class="card-title">Database Migration</h2>
                </div>
                <p class="card-description">
                    Run pending database migrations and update schema
                </p>
                <a href="{{ env('APP_URL') }}/migrate" class="btn btn-danger" target="_blank">
                    <span class="btn-icon">⚠</span>
                    <span>Run Migration</span>
                </a>
                <span class="status-badge">⚠ Use with caution</span>
            </div>

            <!-- Rule Outcome Sync Card -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon icon-primary">
                        🔄
                    </div>
                    <h2 class="card-title">Rule Outcome Sync</h2>
                </div>
                <p class="card-description">
                    Sync old AI-processed reviews that are missing records in the new outcome system
                </p>
                <a href="{{ route('sync.outcomes') }}" class="btn btn-primary" target="_blank">
                    <span class="btn-icon">🔗</span>
                    <span>Sync Outcomes</span>
                </a>
                <span class="status-badge">Data Migration</span>
            </div>

            <!-- Dashboard Rule Backfill Card -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon icon-primary">
                        📈
                    </div>
                    <h2 class="card-title">Dashboard Rule Backfill</h2>
                </div>
                <p class="card-description">
                    Automatically backfill AI rule outcomes across all active businesses to populate dashboard boxes
                </p>
                <a href="{{ route('backfill.dashboard.rules') }}" class="btn btn-primary" target="_blank">
                    <span class="btn-icon">⚡</span>
                    <span>Run Backfill</span>
                </a>
                <span class="status-badge">Dashboard Fix</span>
            </div>

            <!-- Add Missing Reviews Card -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon icon-secondary">
                        ✨
                    </div>
                    <h2 class="card-title">Add Missing Reviews</h2>
                </div>
                <p class="card-description">
                    Instantly create pre-computed reviews for your active business (Owner: rifat...) to populate remaining 0 dashboard values without calling OpenAI.
                </p>
                <a href="{{ route('add.missing.reviews') }}" class="btn btn-secondary" target="_blank">
                    <span class="btn-icon">➕</span>
                    <span>Populate Zero Values</span>
                </a>
                <span class="status-badge">Test Data Fix</span>
            </div>
        </div>

        <!-- Footer -->
        <footer class="footer">
            <p>&copy; {{ date('Y') }} Review System. Built with Laravel & Pure CSS.</p>
        </footer>
    </div>
</body>

</html>