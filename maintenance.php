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
        :root {
            --bg: #020914;
            --panel: rgba(5, 24, 44, 0.82);
            --cyan: #27d7e8;
            --blue: #4aa3ff;
            --amber: #f5c45a;
            --text: #edf8ff;
            --muted: #9fb8ca;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            overflow: hidden;
            color: var(--text);
            font-family: Arial, sans-serif;
            background:
                radial-gradient(circle at 50% 110%, rgba(39, 215, 232, 0.18), transparent 42%),
                radial-gradient(circle at 80% 18%, rgba(74, 163, 255, 0.14), transparent 28%),
                linear-gradient(180deg, #020914 0%, #04182c 48%, #062f42 100%);
        }

        body::before,
        body::after {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
        }

        body::before {
            background:
                linear-gradient(rgba(39, 215, 232, 0.08) 1px, transparent 1px),
                linear-gradient(90deg, rgba(39, 215, 232, 0.08) 1px, transparent 1px);
            background-size: 72px 72px;
            mask-image: linear-gradient(to bottom, transparent, #000 24%, #000 88%, transparent);
            animation: grid-drift 9s linear infinite;
        }

        body::after {
            background:
                radial-gradient(circle, rgba(255,255,255,0.22) 0 1px, transparent 2px) 8% 18% / 140px 140px,
                radial-gradient(circle, rgba(255,255,255,0.16) 0 1px, transparent 2px) 62% 34% / 190px 190px,
                radial-gradient(circle, rgba(255,255,255,0.12) 0 1px, transparent 2px) 86% 68% / 120px 120px;
            opacity: 0.5;
            animation: bubbles 13s linear infinite;
        }

        .scene {
            position: relative;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 28px;
            isolation: isolate;
        }

        .maintenance-card {
            position: relative;
            width: min(760px, 100%);
            padding: clamp(24px, 4vw, 42px);
            border: 1px solid rgba(74, 163, 255, 0.34);
            border-radius: 10px;
            background: linear-gradient(145deg, rgba(4, 18, 35, 0.94), var(--panel));
            box-shadow:
                0 30px 90px rgba(0, 0, 0, 0.44),
                inset 0 0 40px rgba(39, 215, 232, 0.06);
            text-align: center;
            z-index: 3;
        }

        .maintenance-card::before {
            content: "";
            position: absolute;
            inset: 10px;
            border: 1px solid rgba(39, 215, 232, 0.2);
            border-radius: 7px;
            pointer-events: none;
        }

        .status-chip {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
            padding: 8px 14px;
            border: 1px solid rgba(245, 196, 90, 0.45);
            border-radius: 999px;
            color: #ffe3a3;
            background: rgba(245, 196, 90, 0.1);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .status-dot {
            width: 9px;
            height: 9px;
            border-radius: 999px;
            background: var(--amber);
            box-shadow: 0 0 18px var(--amber);
            animation: blink 1.2s ease-in-out infinite;
        }

        h1 {
            margin: 0 0 14px;
            font-size: clamp(30px, 6vw, 54px);
            letter-spacing: 0;
            line-height: 1.05;
        }

        p {
            max-width: 560px;
            margin: 0 auto;
            color: var(--muted);
            font-size: 16px;
            line-height: 1.7;
        }

        .system-line {
            width: min(460px, 82%);
            height: 2px;
            margin: 28px auto 0;
            background: linear-gradient(90deg, transparent, var(--cyan), var(--blue), transparent);
            box-shadow: 0 0 24px rgba(39, 215, 232, 0.65);
            animation: scan 2.2s ease-in-out infinite;
        }

        .railgun {
            position: absolute;
            left: clamp(18px, 7vw, 120px);
            bottom: clamp(60px, 12vh, 150px);
            width: clamp(150px, 22vw, 310px);
            height: clamp(54px, 8vw, 88px);
            z-index: 2;
        }

        .railgun::before {
            content: "";
            position: absolute;
            left: 0;
            bottom: 0;
            width: 42%;
            height: 58%;
            border-radius: 6px 16px 10px 6px;
            background: linear-gradient(135deg, #17324d, #081828);
            border: 1px solid rgba(74, 163, 255, 0.35);
            box-shadow: inset 0 0 18px rgba(39, 215, 232, 0.14);
        }

        .barrel {
            position: absolute;
            left: 30%;
            top: 26%;
            width: 70%;
            height: 15%;
            border-radius: 999px;
            background: linear-gradient(90deg, #284d6d, #75dff0 52%, #15304b);
            box-shadow: 0 0 18px rgba(39, 215, 232, 0.45);
        }

        .charge {
            position: absolute;
            right: 2%;
            top: 10%;
            width: 28px;
            height: 28px;
            border-radius: 999px;
            background: var(--cyan);
            box-shadow: 0 0 28px var(--cyan), 0 0 58px var(--blue);
            animation: charge 3s ease-in-out infinite;
        }

        .beam {
            position: absolute;
            left: 22%;
            right: 18%;
            top: 54%;
            height: 4px;
            z-index: 1;
            transform: rotate(-12deg);
            transform-origin: left center;
            background: linear-gradient(90deg, transparent, #eaffff, var(--cyan), transparent);
            box-shadow: 0 0 16px var(--cyan), 0 0 46px var(--blue);
            opacity: 0;
            animation: fire 3s ease-in-out infinite;
        }

        .ship {
            position: absolute;
            right: clamp(24px, 10vw, 160px);
            top: clamp(76px, 18vh, 180px);
            width: clamp(92px, 15vw, 190px);
            height: clamp(38px, 6vw, 72px);
            z-index: 1;
            animation: incoming 7s ease-in-out infinite;
        }

        .ship::before {
            content: "";
            position: absolute;
            inset: 14% 0 16%;
            clip-path: polygon(0 50%, 22% 12%, 76% 0, 100% 50%, 76% 100%, 22% 88%);
            background: linear-gradient(135deg, #c7d7e8, #4c647c 60%, #152438);
            box-shadow: 0 0 24px rgba(255,255,255,0.14);
        }

        .ship::after {
            content: "";
            position: absolute;
            right: 86%;
            top: 42%;
            width: 44%;
            height: 16%;
            border-radius: 999px;
            background: linear-gradient(90deg, transparent, rgba(245, 196, 90, 0.82));
            filter: blur(1px);
        }

        .impact {
            position: absolute;
            right: clamp(82px, 15vw, 240px);
            top: clamp(104px, 22vh, 215px);
            width: 46px;
            height: 46px;
            border-radius: 999px;
            border: 2px solid rgba(245, 196, 90, 0.86);
            box-shadow: 0 0 26px rgba(245, 196, 90, 0.9);
            opacity: 0;
            animation: impact 3s ease-in-out infinite;
        }

        @keyframes grid-drift {
            to { background-position: 0 72px, 72px 0; }
        }

        @keyframes bubbles {
            to { transform: translateY(-120px); }
        }

        @keyframes blink {
            50% { opacity: 0.35; transform: scale(0.72); }
        }

        @keyframes scan {
            50% { width: min(560px, 94%); opacity: 0.64; }
        }

        @keyframes charge {
            0%, 54%, 100% { transform: scale(0.42); opacity: 0.3; }
            70% { transform: scale(1.18); opacity: 1; }
        }

        @keyframes fire {
            0%, 68%, 78%, 100% { opacity: 0; }
            70%, 74% { opacity: 1; }
        }

        @keyframes impact {
            0%, 70%, 100% { opacity: 0; transform: scale(0.2); }
            76% { opacity: 1; transform: scale(1.4); }
            86% { opacity: 0; transform: scale(2.4); }
        }

        @keyframes incoming {
            0%, 100% { transform: translate(0, 0) rotate(-7deg); }
            50% { transform: translate(-18px, 12px) rotate(-4deg); }
        }

        @media (max-width: 720px) {
            .railgun,
            .ship,
            .beam,
            .impact {
                opacity: 0.58;
            }

            .maintenance-card {
                margin-top: 38px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.001ms !important;
                animation-iteration-count: 1 !important;
            }
        }
    </style>
</head>
<body>
    <div class="scene">
        <div class="ship" aria-hidden="true"></div>
        <div class="impact" aria-hidden="true"></div>
        <div class="beam" aria-hidden="true"></div>
        <div class="railgun" aria-hidden="true">
            <div class="barrel"></div>
            <div class="charge"></div>
        </div>

        <main class="maintenance-card">
            <div class="status-chip"><span class="status-dot"></span> Database link interrupted</div>
            <h1>We are maintaining the system</h1>
            <p>
                The database is temporarily unavailable while our defense systems stabilize the connection.
                Please come back in a few minutes.
            </p>
            <div class="system-line" aria-hidden="true"></div>
        </main>
    </div>
</body>
</html>
