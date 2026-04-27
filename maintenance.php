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
            --deep: #020811;
            --sea: #053044;
            --aqua: #24e5dd;
            --green: #65ffb7;
            --amber: #ffc85a;
            --text: #effbff;
            --muted: #a7c1d3;
            --panel: rgba(4, 19, 36, 0.82);
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
                radial-gradient(circle at 24% 14%, rgba(101, 255, 183, 0.12), transparent 26%),
                radial-gradient(circle at 75% 22%, rgba(36, 229, 221, 0.16), transparent 30%),
                linear-gradient(180deg, #020811 0%, #05192b 42%, #082f44 61%, #041723 100%);
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
                radial-gradient(circle, rgba(255,255,255,0.42) 0 1px, transparent 2px) 14% 19% / 160px 160px,
                radial-gradient(circle, rgba(255,255,255,0.24) 0 1px, transparent 2px) 68% 30% / 220px 220px,
                radial-gradient(circle, rgba(255,255,255,0.16) 0 1px, transparent 2px) 88% 62% / 130px 130px;
            opacity: 0.55;
            animation: particles 18s linear infinite;
        }

        body::after {
            top: auto;
            height: 43vh;
            background:
                linear-gradient(rgba(36, 229, 221, 0.12) 1px, transparent 1px),
                linear-gradient(90deg, rgba(36, 229, 221, 0.08) 1px, transparent 1px),
                linear-gradient(180deg, rgba(5, 48, 68, 0), rgba(3, 16, 27, 0.86));
            background-size: 80px 36px, 80px 36px, auto;
            transform: perspective(420px) rotateX(58deg) translateY(44px);
            transform-origin: bottom center;
            animation: sea-grid 7s linear infinite;
        }

        .scene {
            position: relative;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 26px;
            isolation: isolate;
        }

        .horizon {
            position: absolute;
            left: 0;
            right: 0;
            top: 56%;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(36, 229, 221, 0.72), transparent);
            box-shadow: 0 0 26px rgba(36, 229, 221, 0.45);
            z-index: 0;
        }

        .ship {
            position: absolute;
            right: -18vw;
            top: clamp(64px, 13vh, 150px);
            width: clamp(120px, 18vw, 240px);
            height: clamp(42px, 6vw, 84px);
            z-index: 2;
            animation: ship-pass 6.6s cubic-bezier(.45,.02,.55,.95) infinite;
        }

        .ship::before {
            content: "";
            position: absolute;
            inset: 10% 0 18%;
            clip-path: polygon(0 50%, 14% 24%, 48% 0, 82% 18%, 100% 52%, 78% 78%, 38% 100%, 13% 78%);
            background: linear-gradient(135deg, #d9e8f7 0%, #8296aa 42%, #27384a 100%);
            box-shadow: 0 0 22px rgba(255,255,255,0.18);
        }

        .ship::after {
            content: "";
            position: absolute;
            right: 88%;
            top: 43%;
            width: 42%;
            height: 14%;
            border-radius: 999px;
            background: linear-gradient(90deg, transparent, rgba(255, 200, 90, 0.92));
            filter: blur(1px);
        }

        .platform {
            position: absolute;
            left: clamp(20px, 9vw, 140px);
            bottom: clamp(72px, 14vh, 155px);
            width: clamp(180px, 25vw, 340px);
            height: clamp(86px, 13vw, 172px);
            z-index: 2;
        }

        .island {
            position: absolute;
            left: 0;
            right: 10%;
            bottom: 0;
            height: 32%;
            clip-path: polygon(0 100%, 10% 38%, 30% 26%, 48% 0, 72% 28%, 88% 44%, 100% 100%);
            background: linear-gradient(180deg, #183145, #07121e);
            border-bottom: 1px solid rgba(36, 229, 221, 0.35);
        }

        .tower {
            position: absolute;
            left: 39%;
            bottom: 18%;
            width: 24%;
            height: 88%;
            transform-origin: bottom center;
            animation: tower-wake 6.6s ease-in-out infinite;
        }

        .tower-core {
            position: absolute;
            left: 34%;
            bottom: 0;
            width: 32%;
            height: 100%;
            clip-path: polygon(28% 0, 72% 0, 100% 100%, 0 100%);
            background: linear-gradient(180deg, #1d5661, #0a1a2a 72%);
            border: 1px solid rgba(101, 255, 183, 0.34);
            box-shadow: inset 0 0 24px rgba(36, 229, 221, 0.18);
        }

        .tower-arm {
            position: absolute;
            left: 24%;
            top: 10%;
            width: 52%;
            height: 30%;
            transform-origin: bottom center;
        }

        .tower-arm::before,
        .tower-arm::after {
            content: "";
            position: absolute;
            bottom: 0;
            width: 46%;
            height: 100%;
            background: linear-gradient(180deg, #27727b, #0a1c2d);
            border: 1px solid rgba(101, 255, 183, 0.36);
            box-shadow: 0 0 18px rgba(36, 229, 221, 0.16);
            animation: arms-open 6.6s ease-in-out infinite;
        }

        .tower-arm::before {
            left: 0;
            clip-path: polygon(100% 0, 24% 16%, 0 100%, 100% 100%);
            transform-origin: right bottom;
        }

        .tower-arm::after {
            right: 0;
            clip-path: polygon(0 0, 76% 16%, 100% 100%, 0 100%);
            transform-origin: left bottom;
        }

        .tower-charge {
            position: absolute;
            left: 50%;
            top: 6%;
            width: 18px;
            height: 18px;
            margin-left: -9px;
            border-radius: 999px;
            background: #d8fff2;
            box-shadow:
                0 0 18px var(--green),
                0 0 54px var(--aqua),
                0 0 96px rgba(101, 255, 183, 0.44);
            opacity: 0;
            animation: tower-charge 6.6s ease-in-out infinite;
        }

        .beam {
            position: absolute;
            left: clamp(160px, 24vw, 360px);
            top: clamp(104px, 20vh, 210px);
            width: 62vw;
            height: 7px;
            border-radius: 999px;
            transform: rotate(-18deg);
            transform-origin: left center;
            background: linear-gradient(90deg, #f4fffb, var(--green), var(--aqua), transparent);
            box-shadow:
                0 0 18px var(--green),
                0 0 52px var(--aqua),
                0 0 90px rgba(101, 255, 183, 0.48);
            opacity: 0;
            z-index: 1;
            animation: beam-fire 6.6s ease-in-out infinite;
        }

        .shockwave {
            position: absolute;
            right: clamp(72px, 20vw, 330px);
            top: clamp(82px, 16vh, 168px);
            width: 60px;
            height: 60px;
            border-radius: 999px;
            border: 2px solid rgba(255, 232, 160, 0.88);
            background: radial-gradient(circle, rgba(255,255,255,0.9), rgba(101,255,183,0.4) 28%, transparent 66%);
            box-shadow: 0 0 34px rgba(255, 200, 90, 0.92);
            opacity: 0;
            z-index: 2;
            animation: impact 6.6s ease-in-out infinite;
        }

        .maintenance-card {
            position: relative;
            width: min(760px, 100%);
            margin-top: clamp(46px, 9vh, 110px);
            padding: clamp(24px, 4vw, 42px);
            border: 1px solid rgba(36, 229, 221, 0.32);
            border-radius: 10px;
            background: linear-gradient(145deg, rgba(3, 14, 27, 0.95), var(--panel));
            box-shadow:
                0 30px 90px rgba(0, 0, 0, 0.46),
                inset 0 0 44px rgba(36, 229, 221, 0.08);
            text-align: center;
            z-index: 4;
        }

        .maintenance-card::before {
            content: "";
            position: absolute;
            inset: 10px;
            border: 1px solid rgba(101, 255, 183, 0.22);
            border-radius: 7px;
            pointer-events: none;
        }

        .status-chip {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
            padding: 8px 14px;
            border: 1px solid rgba(255, 200, 90, 0.46);
            border-radius: 999px;
            color: #ffe6ad;
            background: rgba(255, 200, 90, 0.1);
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
            max-width: 580px;
            margin: 0 auto;
            color: var(--muted);
            font-size: 16px;
            line-height: 1.7;
        }

        .system-line {
            width: min(460px, 82%);
            height: 2px;
            margin: 28px auto 0;
            background: linear-gradient(90deg, transparent, var(--green), var(--aqua), transparent);
            box-shadow: 0 0 24px rgba(36, 229, 221, 0.72);
            animation: scan 2.2s ease-in-out infinite;
        }

        @keyframes particles {
            to { transform: translateY(-120px); }
        }

        @keyframes sea-grid {
            to { background-position: 0 36px, 80px 36px, 0 0; }
        }

        @keyframes ship-pass {
            0% { transform: translate(0, 0) rotate(-7deg); opacity: 0; }
            14% { opacity: 1; }
            62% { transform: translate(-54vw, 15vh) rotate(-5deg); opacity: 1; }
            67% { transform: translate(-56vw, 16vh) rotate(-2deg) scale(1.04); opacity: 1; }
            76% { transform: translate(-58vw, 17vh) rotate(4deg) scale(0.96); opacity: 0; }
            100% { transform: translate(-62vw, 20vh) rotate(8deg); opacity: 0; }
        }

        @keyframes tower-wake {
            0%, 30%, 100% { transform: translateY(16px); }
            45%, 78% { transform: translateY(0); }
        }

        @keyframes arms-open {
            0%, 38%, 100% { transform: rotate(0deg); }
            50%, 78% { transform: rotate(var(--arm-rotation, 18deg)); }
        }

        .tower-arm::before { --arm-rotation: -26deg; }
        .tower-arm::after { --arm-rotation: 26deg; }

        @keyframes tower-charge {
            0%, 38%, 100% { opacity: 0; transform: scale(0.2); }
            54% { opacity: 0.7; transform: scale(0.85); }
            62% { opacity: 1; transform: scale(1.25); }
            72% { opacity: 0.35; transform: scale(0.55); }
        }

        @keyframes beam-fire {
            0%, 62%, 71%, 100% { opacity: 0; }
            64%, 67% { opacity: 1; }
        }

        @keyframes impact {
            0%, 63%, 100% { opacity: 0; transform: scale(0.15); }
            67% { opacity: 1; transform: scale(1.2); }
            78% { opacity: 0; transform: scale(3.1); }
        }

        @keyframes blink {
            50% { opacity: 0.35; transform: scale(0.72); }
        }

        @keyframes scan {
            50% { width: min(560px, 94%); opacity: 0.64; }
        }

        @media (max-width: 760px) {
            .platform,
            .ship,
            .beam,
            .shockwave {
                opacity: 0.62;
            }

            .maintenance-card {
                margin-top: 80px;
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
        <div class="horizon" aria-hidden="true"></div>
        <div class="ship" aria-hidden="true"></div>
        <div class="beam" aria-hidden="true"></div>
        <div class="shockwave" aria-hidden="true"></div>
        <div class="platform" aria-hidden="true">
            <div class="island"></div>
            <div class="tower">
                <div class="tower-core"></div>
                <div class="tower-arm"></div>
                <div class="tower-charge"></div>
            </div>
        </div>

        <main class="maintenance-card">
            <div class="status-chip"><span class="status-dot"></span> Database link interrupted</div>
            <h1>We are maintaining the system</h1>
            <p>
                The database beacon is temporarily offline while the defense platform stabilizes the connection.
                Please come back in a few minutes.
            </p>
            <div class="system-line" aria-hidden="true"></div>
        </main>
    </div>
</body>
</html>
