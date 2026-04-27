<?php
if (!headers_sent()) {
    http_response_code(503);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance - E-Wallet</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: #071426;
            color: #e5edf7;
            font-family: Arial, sans-serif;
        }
        main {
            width: min(520px, calc(100% - 32px));
            padding: 32px;
            border: 1px solid rgba(125, 211, 252, 0.25);
            border-radius: 8px;
            background: rgba(15, 35, 62, 0.92);
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.35);
            text-align: center;
        }
        h1 {
            margin: 0 0 12px;
            font-size: 28px;
        }
        p {
            margin: 0;
            line-height: 1.6;
            color: #b7c7d8;
        }
    </style>
</head>
<body>
    <main>
        <h1>We are maintaining the system</h1>
        <p>The database is temporarily unavailable. Please come back in a few minutes.</p>
    </main>
</body>
</html>
