<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HelloOtel — Sign in</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0f172a;
            color: #f1f5f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }
        .logo { text-align: center; margin-bottom: 28px; }
        .logo h1 { font-size: 22px; font-weight: 700; color: #f59e0b; }
        .logo p  { font-size: 13px; color: #94a3b8; margin-top: 4px; }
        label { display: block; font-size: 13px; color: #94a3b8; margin-bottom: 6px; }
        input {
            width: 100%;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 10px 14px;
            color: #f1f5f9;
            font-size: 15px;
            outline: none;
            margin-bottom: 4px;
        }
        input:focus { border-color: #f59e0b; }
        .field { margin-bottom: 18px; }
        .error { color: #f87171; font-size: 13px; margin-top: 4px; }
        button[type=submit] {
            width: 100%;
            background: #f59e0b;
            color: #0f172a;
            border: none;
            border-radius: 8px;
            padding: 11px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 6px;
        }
        button[type=submit]:hover { background: #fbbf24; }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">
        <h1>HelloOtel Portal</h1>
        <p>Sign in to view your bookings</p>
    </div>

    <form method="POST" action="{{ route('portal.authenticate') }}">
        @csrf
        <div class="field">
            <label for="username">Username</label>
            <input
                type="text"
                id="username"
                name="username"
                value="{{ old('username') }}"
                autocomplete="username"
                autofocus
                required
            >
            @error('username')
                <div class="error">{{ $message ?: 'Invalid username or password.' }}</div>
            @enderror
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                required
            >
        </div>
        <button type="submit">Sign in</button>
    </form>
</div>
</body>
</html>
