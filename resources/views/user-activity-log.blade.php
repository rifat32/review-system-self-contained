<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Activity Logs</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --border-color: #334155;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --accent: #10b981;
            --accent-hover: #059669;
            --error: #ef4444;
            --font-main: 'Inter', sans-serif;
            --font-mono: 'Fira Code', monospace;
        }

        body {
            font-family: var(--font-main);
            background-color: var(--bg-color);
            color: var(--text-main);
            margin: 0;
            padding: 2rem;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 1rem;
        }

        .page-title {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--text-main);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .page-title i { color: var(--accent); }

        /* Filter Section */
        .filter-card {
            background-color: var(--card-bg);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            align-items: flex-end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-muted);
        }

        .form-control {
            background-color: var(--bg-color);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            font-family: var(--font-main);
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
        }

        .btn-group {
            display: flex;
            gap: 1rem;
        }

        .btn {
            background-color: var(--accent);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-family: var(--font-main);
            flex: 1;
        }

        .btn:hover { background-color: var(--accent-hover); }

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-main);
            text-decoration: none;
        }
        
        .btn-outline:hover { background-color: var(--border-color); }

        /* Table Section */
        .table-card {
            background-color: var(--card-bg);
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            background-color: rgba(15, 23, 42, 0.5);
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
        }

        td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.875rem;
            vertical-align: middle;
        }

        .tr-main {
            transition: background-color 0.2s;
            cursor: pointer;
        }

        .tr-main:hover { background-color: rgba(255, 255, 255, 0.02); }
        
        .tr-main.is-error td:first-child { border-left: 3px solid var(--error); }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            background-color: var(--border-color);
            color: var(--text-main);
        }

        .badge.error {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--error);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .badge.method-get { background-color: rgba(59, 130, 246, 0.1); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.2); }
        .badge.method-post { background-color: rgba(16, 185, 129, 0.1); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.2); }
        .badge.method-put, .badge.method-patch { background-color: rgba(245, 158, 11, 0.1); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.2); }
        .badge.method-delete { background-color: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2); }

        .truncate-text {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
        }

        .expand-icon {
            color: var(--text-muted);
            transition: transform 0.3s ease;
        }

        .tr-main.expanded .expand-icon {
            transform: rotate(180deg);
            color: var(--accent);
        }

        /* Details Section */
        .tr-details {
            display: none;
            background-color: rgba(15, 23, 42, 0.3);
        }

        .tr-details.active {
            display: table-row;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .details-container {
            padding: 1.5rem;
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        
        @media (min-width: 1024px) {
            .details-container { grid-template-columns: 1fr 1fr; }
            .col-full { grid-column: span 2; }
        }

        .detail-block {
            background-color: var(--bg-color);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            padding: 1.25rem;
        }

        .detail-title {
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--text-muted);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .detail-content {
            font-family: var(--font-mono);
            font-size: 0.8125rem;
            color: #e2e8f0;
            white-space: pre-wrap;
            word-break: break-all;
            margin: 0;
            max-height: 400px;
            overflow-y: auto;
            background: rgba(0,0,0,0.2);
            padding: 1rem;
            border-radius: 0.25rem;
        }
        
        .detail-content.error-msg {
            color: #fca5a5;
            font-family: var(--font-main);
            font-weight: 500;
            font-size: 0.875rem;
            background: rgba(239, 68, 68, 0.05);
            border-left: 3px solid var(--error);
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-color); }
        ::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #475569; }

        /* Pagination Styles to override Laravel's default */
        .pagination-wrapper { padding: 1.5rem; border-top: 1px solid var(--border-color); display: flex; justify-content: center; }
        .pagination { display: flex; list-style: none; padding: 0; margin: 0; gap: 0.25rem; }
        .page-item .page-link { padding: 0.5rem 0.75rem; background: var(--bg-color); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 0.25rem; text-decoration: none; }
        .page-item.active .page-link { background: var(--accent); border-color: var(--accent); }
        .page-item.disabled .page-link { opacity: 0.5; pointer-events: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fa-solid fa-shield-halved"></i>
                Activity & Error Logs
            </h1>
        </div>

        <!-- FILTER SECTION -->
        <div class="filter-card">
            <form action="{{ url()->current() }}" method="GET" class="filter-form">
                <div class="form-group">
                    <label class="form-label">Error ID</label>
                    <input type="text" name="id" class="form-control" placeholder="Search by ID" value="{{ request('id') }}">
                </div>
                
                <div class="form-group">
                    <label class="form-label">User ID</label>
                    <input type="text" name="user_id" class="form-control" placeholder="Search by User ID" value="{{ request('user_id') }}">
                </div>

                <div class="form-group">
                    <label class="form-label">Log Type (Is Error)</label>
                    <select name="is_error" class="form-control">
                        <option value="">All Logs</option>
                        <option value="1" {{ request('is_error', '1') == '1' ? 'selected' : '' }}>Errors Only (True)</option>
                        <option value="0" {{ request('is_error') == '0' ? 'selected' : '' }}>Standard Logs (False)</option>
                    </select>
                </div>

                <div class="form-group btn-group">
                    <button type="submit" class="btn">
                        <i class="fa-solid fa-filter"></i> Filter
                    </button>
                    <a href="{{ url()->current() }}" class="btn btn-outline">
                        <i class="fa-solid fa-rotate-right"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- TABLE SECTION -->
        <div class="table-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th width="40"></th>
                            <th>Error ID</th>
                            <th>Date & Time</th>
                            <th>User ID</th>
                            <th>IP Address</th>
                            <th>Method</th>
                            <th>API URL</th>
                            <th>Device</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($activity_logs as $log)
                            <!-- Main Row -->
                            <tr class="tr-main {{ $log->is_error ? 'is-error' : '' }}" onclick="toggleDetails({{ $log->id }})">
                                <td style="text-align: center;">
                                    <i class="fa-solid fa-chevron-down expand-icon" id="icon-{{ $log->id }}"></i>
                                </td>
                                <td>
                                    <span class="badge {{ $log->is_error ? 'error' : '' }}">#{{ $log->id }}</span>
                                </td>
                                <td>
                                    <span style="color: var(--text-muted); font-size: 0.75rem;">{{ $log->created_at->format('Y-m-d') }}</span><br>
                                    <strong>{{ $log->created_at->format('H:i:s') }}</strong>
                                </td>
                                <td>{{ $log->user_id ?: 'Guest' }}</td>
                                <td><span style="font-family: var(--font-mono); font-size: 0.75rem;">{{ $log->ip_address }}</span></td>
                                <td>
                                    <span class="badge method-{{ strtolower($log->request_method) }}">
                                        {{ $log->request_method ?: 'N/A' }}
                                    </span>
                                </td>
                                <td>
                                    <span>{{ $log->api_url }}</span>
                                </td>
                                <td>
                                    <span class="truncate-text" title="{{ $log->device }}">{{ $log->device ?: 'Unknown' }}</span>
                                </td>
                            </tr>
                            
                            <!-- Expandable Details Row -->
                            <tr class="tr-details" id="details-{{ $log->id }}">
                                <td colspan="8" style="padding: 0; border: none;">
                                    <div class="details-container">
                                        
                                        <!-- Message / Error Box -->
                                        @if($log->message)
                                        <div class="detail-block col-full">
                                            <div class="detail-title">
                                                <i class="fa-solid fa-triangle-exclamation" style="color: var(--error)"></i> Message
                                            </div>
                                            <pre class="detail-content error-msg">{{ $log->message }}</pre>
                                        </div>
                                        @endif

                                        <!-- Payload Data -->
                                        <div class="detail-block">
                                            <div class="detail-title">
                                                <i class="fa-solid fa-box-open"></i> Request Payload
                                            </div>
                                            <pre class="detail-content">@if($log->payload)@php
                                                    $payloadObj = json_decode($log->payload);
                                                    echo $payloadObj ? json_encode($payloadObj, JSON_PRETTY_PRINT) : $log->payload;
                                                @endphp@else{ "empty": true }@endif</pre>
                                        </div>

                                        <!-- Queries -->
                                        <div class="detail-block">
                                            <div class="detail-title">
                                                <i class="fa-solid fa-link"></i> Route Queries (Params)
                                            </div>
                                            <pre class="detail-content">@if($log->queries)@php
                                                    $queriesObj = json_decode($log->queries);
                                                    echo $queriesObj ? json_encode($queriesObj, JSON_PRETTY_PRINT) : $log->queries;
                                                @endphp@else{ "empty": true }@endif</pre>
                                        </div>

                                        <!-- Token -->
                                        <div class="detail-block">
                                            <div class="detail-title">
                                                <i class="fa-solid fa-key"></i> Bearer Token
                                            </div>
                                            <pre class="detail-content" style="word-break: break-all;">{{ $log->token ?: 'No Token Provided' }}</pre>
                                        </div>

                                        <!-- Full Stack Trace -->
                                        @if($log->error_trace)
                                        <div class="detail-block col-full">
                                            <div class="detail-title">
                                                <i class="fa-solid fa-bug"></i> Error Stack Trace
                                            </div>
                                            <pre class="detail-content" style="font-size: 0.75rem;">{{ $log->error_trace }}</pre>
                                        </div>
                                        @endif

                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 3rem;">
                                    <div style="color: var(--text-muted); font-size: 1.125rem;">
                                        <i class="fa-solid fa-folder-open" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                                        No activity logs found.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            @if($activity_logs->hasPages())
            <div class="pagination-wrapper">
                {{ $activity_logs->withQueryString()->links('pagination::bootstrap-4') }}
            </div>
            @endif
        </div>
    </div>

    <script>
        function toggleDetails(id) {
            // Find the details row and the icon
            const detailsRow = document.getElementById('details-' + id);
            const mainRow = detailsRow.previousElementSibling;
            
            // Toggle active classes
            if (detailsRow.classList.contains('active')) {
                detailsRow.classList.remove('active');
                mainRow.classList.remove('expanded');
            } else {
                // Optional: Close all other open rows first for cleaner UI
                // document.querySelectorAll('.tr-details.active').forEach(row => {
                //     row.classList.remove('active');
                //     row.previousElementSibling.classList.remove('expanded');
                // });
                
                detailsRow.classList.add('active');
                mainRow.classList.add('expanded');
            }
        }
    </script>
</body>
</html>
