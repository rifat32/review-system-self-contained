<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>API Tester - Review System</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Centralized CSS Design System -->
    <link rel="stylesheet" href="{{ asset('css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('css/common.css') }}">

    <style>
        /* ==================== PAGE-SPECIFIC STYLES ==================== */

        /* Animated background */
        body::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 50% 50%, rgba(99, 102, 241, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(139, 92, 246, 0.1) 0%, transparent 50%);
            animation: backgroundPulse 20s ease infinite;
            z-index: -1;
        }

        @keyframes backgroundPulse {
            0%,
            100% {
                transform: translate(0, 0) rotate(0deg);
            }

            50% {
                transform: translate(3%, 3%) rotate(180deg);
            }
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: var(--spacing-xl);
            animation: fadeInDown 0.6s ease;
        }

        .header-title {
            font-size: 2.5rem;
            font-weight: var(--font-bold);
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: var(--spacing-xs);
        }

        .header-subtitle {
            font-size: var(--text-base);
            color: var(--text-secondary);
        }

        /* Main layout */
        .api-tester-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
        }

        /* Form card */
        .form-card {
            background: var(--bg-card);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: var(--spacing-lg);
            animation: fadeInUp 0.6s ease;
        }

        .form-group {
            margin-bottom: var(--spacing-md);
        }

        .form-label {
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
            font-weight: var(--font-semibold);
            color: var(--text-primary);
            margin-bottom: var(--spacing-xs);
            font-size: var(--text-sm);
        }

        .form-label-icon {
            font-size: var(--text-lg);
        }

        .form-input,
        .form-textarea,
        .form-select {
            width: 100%;
            padding: var(--spacing-sm);
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-size: var(--text-sm);
            font-family: var(--font-mono);
            transition: all var(--transition-base);
        }

        .form-input:focus,
        .form-textarea:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .form-textarea {
            min-height: 120px;
            resize: vertical;
            font-family: var(--font-mono);
        }

        /* Method select with color coding */
        .method-select {
            font-weight: var(--font-semibold);
        }

        /* Response card */
        .response-card {
            background: var(--bg-card);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: var(--spacing-lg);
            animation: fadeInUp 0.6s ease 0.1s both;
            display: flex;
            flex-direction: column;
        }

        .response-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: var(--spacing-md);
        }

        .response-title {
            font-size: var(--text-lg);
            font-weight: var(--font-semibold);
            color: var(--text-primary);
        }

        .response-status {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-xs);
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: var(--text-xs);
            font-weight: var(--font-semibold);
        }

        .status-success {
            background: rgba(16, 185, 129, 0.2);
            color: var(--secondary-lighter);
        }

        .status-error {
            background: rgba(239, 68, 68, 0.2);
            color: var(--danger-lighter);
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.2);
            color: var(--warning-lighter);
        }

        .response-body {
            flex: 1;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: var(--spacing-md);
            overflow-x: auto;
            font-family: var(--font-mono);
            font-size: var(--text-sm);
            color: var(--text-primary);
            line-height: var(--leading-relaxed);
            white-space: pre-wrap;
            word-break: break-all;
            min-height: 300px;
            max-height: 600px;
            overflow-y: auto;
        }

        .response-placeholder {
            color: var(--text-tertiary);
            font-style: italic;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            flex-direction: column;
            gap: var(--spacing-sm);
        }

        .response-placeholder-icon {
            font-size: 3rem;
            opacity: 0.5;
        }

        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: var(--spacing-sm);
            margin-top: var(--spacing-md);
        }

        .btn-copy {
            padding: var(--spacing-xs) var(--spacing-sm);
            font-size: var(--text-sm);
        }

        /* Quick actions */
        .quick-actions {
            margin-top: var(--spacing-md);
            padding-top: var(--spacing-md);
            border-top: 1px solid var(--border);
        }

        .quick-actions-title {
            font-size: var(--text-sm);
            font-weight: var(--font-semibold);
            color: var(--text-secondary);
            margin-bottom: var(--spacing-sm);
        }

        .quick-action-buttons {
            display: flex;
            gap: var(--spacing-xs);
            flex-wrap: wrap;
        }

        .btn-quick {
            padding: var(--spacing-xs) var(--spacing-sm);
            font-size: var(--text-xs);
            background: rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.3);
            color: var(--primary-lighter);
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: all var(--transition-base);
        }

        .btn-quick:hover {
            background: rgba(99, 102, 241, 0.2);
            transform: translateY(-1px);
        }

        /* Loading spinner */
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid var(--border);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .api-tester-container {
                grid-template-columns: 1fr;
            }
        }

        /* Syntax highlighting for JSON */
        .json-key {
            color: #a5b4fc;
        }

        .json-string {
            color: #6ee7b7;
        }

        .json-number {
            color: #fbbf24;
        }

        .json-boolean {
            color: #f87171;
        }

        .json-null {
            color: #94a3b8;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <h1 class="header-title">API Tester</h1>
            <p class="header-subtitle">Test your API endpoints with ease</p>
        </header>

        <!-- Main Content -->
        <div class="api-tester-container">
            <!-- Form Card -->
            <div class="form-card">
                <form id="apiForm">
                    <!-- API URL -->
                    <div class="form-group">
                        <label class="form-label" for="apiUrl">
                            <span class="form-label-icon">🔗</span>
                            API Endpoint
                        </label>
                        <input type="text" id="apiUrl" class="form-input" placeholder="https://api.example.com/endpoint" required>
                    </div>

                    <!-- Bearer Token -->
                    <div class="form-group">
                        <label class="form-label" for="bearerToken">
                            <span class="form-label-icon">🔑</span>
                            Bearer Token
                        </label>
                        <input type="password" id="bearerToken" class="form-input" placeholder="Enter your authentication token" required>
                    </div>

                    <!-- Request Method -->
                    <div class="form-group">
                        <label class="form-label" for="method">
                            <span class="form-label-icon">⚡</span>
                            Request Method
                        </label>
                        <select id="method" class="form-select method-select" required>
                            <option value="GET">GET - Retrieve data</option>
                            <option value="POST">POST - Create new resource</option>
                            <option value="PUT">PUT - Update resource</option>
                            <option value="PATCH">PATCH - Partial update</option>
                            <option value="DELETE">DELETE - Remove resource</option>
                        </select>
                    </div>

                    <!-- Payload -->
                    <div class="form-group">
                        <label class="form-label" for="payload">
                            <span class="form-label-icon">📦</span>
                            Request Payload (JSON)
                        </label>
                        <textarea id="payload" class="form-textarea" placeholder='{"key": "value"}'></textarea>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-primary btn-full">
                        <span id="submitText">Send Request</span>
                        <span id="submitLoading" style="display: none;">
                            <span class="spinner"></span> Sending...
                        </span>
                    </button>

                    <!-- Quick Actions -->
                    <div class="quick-actions">
                        <div class="quick-actions-title">Quick Actions:</div>
                        <div class="quick-action-buttons">
                            <button type="button" class="btn-quick" onclick="clearForm()">🗑 Clear All</button>
                            <button type="button" class="btn-quick" onclick="formatJSON()">✨ Format JSON</button>
                            <button type="button" class="btn-quick" onclick="toggleToken()">👁 Show/Hide Token</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Response Card -->
            <div class="response-card">
                <div class="response-header">
                    <h3 class="response-title">Response</h3>
                    <span id="responseStatus" class="response-status status-pending">⏱ Waiting</span>
                </div>
                <div id="response" class="response-body">
                    <div class="response-placeholder">
                        <div class="response-placeholder-icon">📡</div>
                        <div>Response will appear here...</div>
                    </div>
                </div>
                <div class="action-buttons">
                    <button type="button" class="btn btn-secondary btn-sm btn-copy" onclick="copyResponse()" style="display: none;" id="copyBtn">
                        📋 Copy Response
                    </button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="clearResponse()" style="display: none;" id="clearBtn">
                        🗑 Clear
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const form = document.getElementById('apiForm');
        const responseDiv = document.getElementById('response');
        const responseStatus = document.getElementById('responseStatus');
        const submitText = document.getElementById('submitText');
        const submitLoading = document.getElementById('submitLoading');
        const copyBtn = document.getElementById('copyBtn');
        const clearBtn = document.getElementById('clearBtn');

        // Form submission
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const apiUrl = document.getElementById('apiUrl').value.trim();
            const bearerToken = document.getElementById('bearerToken').value.trim();
            const payloadRaw = document.getElementById('payload').value.trim();
            const method = document.getElementById('method').value.trim().toUpperCase();

            // Show loading state
            submitText.style.display = 'none';
            submitLoading.style.display = 'inline-flex';
            responseStatus.className = 'response-status status-pending';
            responseStatus.innerHTML = '⏱ Sending...';
            responseDiv.innerHTML = '<div class="response-placeholder"><div class="response-placeholder-icon"><span class="spinner"></span></div><div>Sending request...</div></div>';
            copyBtn.style.display = 'none';
            clearBtn.style.display = 'none';

            try {
                const headers = {
                    'Authorization': `Bearer ${bearerToken}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                };

                let fullUrl = apiUrl;
                let body = undefined;

                if (payloadRaw) {
                    const payloadObj = JSON.parse(payloadRaw);

                    if (method === 'GET') {
                        const queryParams = new URLSearchParams(payloadObj).toString();
                        fullUrl += (fullUrl.includes('?') ? '&' : '?') + queryParams;
                    } else {
                        body = JSON.stringify(payloadObj);
                    }
                }

                const startTime = Date.now();
                const response = await fetch(fullUrl, {
                    method,
                    headers,
                    body: method !== 'GET' ? body : undefined
                });
                const endTime = Date.now();
                const duration = endTime - startTime;

                const contentType = response.headers.get('content-type');
                let responseData;

                if (contentType && contentType.includes('application/json')) {
                    responseData = await response.json();
                    responseDiv.innerHTML = syntaxHighlight(JSON.stringify(responseData, null, 2));
                } else {
                    responseData = await response.text();
                    responseDiv.innerText = responseData;
                }

                // Update status
                if (response.ok) {
                    responseStatus.className = 'response-status status-success';
                    responseStatus.innerHTML = `✅ ${response.status} OK (${duration}ms)`;
                } else {
                    responseStatus.className = 'response-status status-error';
                    responseStatus.innerHTML = `❌ ${response.status} Error (${duration}ms)`;
                }

                copyBtn.style.display = 'inline-flex';
                clearBtn.style.display = 'inline-flex';

            } catch (error) {
                responseDiv.innerHTML = `<span style="color: var(--danger);">❌ Error: ${error.message}</span>`;
                responseStatus.className = 'response-status status-error';
                responseStatus.innerHTML = '❌ Failed';
                clearBtn.style.display = 'inline-flex';
            } finally {
                submitText.style.display = 'inline';
                submitLoading.style.display = 'none';
            }
        });

        // Syntax highlighting for JSON
        function syntaxHighlight(json) {
            json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
                let cls = 'json-number';
                if (/^"/.test(match)) {
                    if (/:$/.test(match)) {
                        cls = 'json-key';
                    } else {
                        cls = 'json-string';
                    }
                } else if (/true|false/.test(match)) {
                    cls = 'json-boolean';
                } else if (/null/.test(match)) {
                    cls = 'json-null';
                }
                return '<span class="' + cls + '">' + match + '</span>';
            });
        }

        // Copy response
        function copyResponse() {
            const text = responseDiv.innerText;
            navigator.clipboard.writeText(text).then(() => {
                const originalText = copyBtn.innerHTML;
                copyBtn.innerHTML = '✅ Copied!';
                setTimeout(() => {
                    copyBtn.innerHTML = originalText;
                }, 2000);
            });
        }

        // Clear response
        function clearResponse() {
            responseDiv.innerHTML = '<div class="response-placeholder"><div class="response-placeholder-icon">📡</div><div>Response will appear here...</div></div>';
            responseStatus.className = 'response-status status-pending';
            responseStatus.innerHTML = '⏱ Waiting';
            copyBtn.style.display = 'none';
            clearBtn.style.display = 'none';
        }

        // Clear form
        function clearForm() {
            document.getElementById('apiUrl').value = '';
            document.getElementById('bearerToken').value = '';
            document.getElementById('payload').value = '';
            document.getElementById('method').value = 'GET';
        }

        // Format JSON
        function formatJSON() {
            const payload = document.getElementById('payload').value.trim();
            if (payload) {
                try {
                    const parsed = JSON.parse(payload);
                    document.getElementById('payload').value = JSON.stringify(parsed, null, 2);
                } catch (e) {
                    alert('Invalid JSON format');
                }
            }
        }

        // Toggle token visibility
        function toggleToken() {
            const tokenInput = document.getElementById('bearerToken');
            tokenInput.type = tokenInput.type === 'password' ? 'text' : 'password';
        }
    </script>
</body>

</html>
