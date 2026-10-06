<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#fd6802">
    <title>Under Maintenance</title>

    <style>
        :root {
            --brand: #fd6802;
            --brand-dark: #d94f00;
            --ink: #17202a;
            --muted: #6f7882;
            --surface: #ffffff;
            --line: #eceff2;
            --soft: #fff3eb;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            min-height: 100%;
        }

        body {
            margin: 0;
            color: var(--ink);
            background: #f7f8fa;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .page {
            min-height: 100vh;
            display: grid;
            place-items: center;
            position: relative;
            overflow: hidden;
            padding: 32px 20px;
        }

        .page::before {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
            opacity: .55;
            background-image: linear-gradient(var(--line) 1px, transparent 1px), linear-gradient(90deg, var(--line) 1px, transparent 1px);
            background-size: 42px 42px;
            mask-image: linear-gradient(to bottom, black, transparent 82%);
        }

        .accent {
            position: absolute;
            width: 460px;
            height: 460px;
            border-radius: 50%;
            top: -270px;
            right: -130px;
            background: var(--brand);
            opacity: .09;
            filter: blur(2px);
        }

        .card {
            position: relative;
            width: min(100%, 900px);
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 28px;
            background: rgba(255, 255, 255, .94);
            box-shadow: 0 24px 70px rgba(23, 32, 42, .10);
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 24px 30px;
            border-bottom: 1px solid var(--line);
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--ink);
            font-size: 15px;
            font-weight: 800;
            letter-spacing: -.02em;
        }

        .brand-mark {
            display: grid;
            width: 36px;
            height: 36px;
            place-items: center;
            border-radius: 11px;
            color: #fff;
            background: var(--brand);
            font-size: 18px;
            font-weight: 900;
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border: 1px solid #ffd8c2;
            border-radius: 999px;
            color: var(--brand-dark);
            background: var(--soft);
            font-size: 12px;
            font-weight: 700;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--brand);
            box-shadow: 0 0 0 4px rgba(253, 104, 2, .13);
        }

        .content {
            padding: 58px 30px 30px;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 0 0 18px;
            color: var(--brand-dark);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .14em;
            text-transform: uppercase;
        }

        .eyebrow-line {
            width: 28px;
            height: 2px;
            background: var(--brand);
        }

        h1 {
            max-width: 640px;
            margin: 0;
            font-size: clamp(42px, 7vw, 78px);
            line-height: .98;
            letter-spacing: -.065em;
        }

        .message {
            max-width: 590px;
            margin: 22px 0 0;
            color: var(--muted);
            font-size: clamp(16px, 2vw, 19px);
            line-height: 1.7;
        }

        .progress-section {
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: end;
            gap: 20px;
            margin-top: 52px;
            padding-top: 25px;
            border-top: 1px solid var(--line);
        }

        .progress-label {
            margin: 0 0 11px;
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
        }

        .progress-value {
            margin: 0 0 11px;
            color: var(--brand-dark);
            font-size: 13px;
            font-weight: 800;
        }

        .progress-track {
            grid-column: 1 / -1;
            height: 9px;
            overflow: hidden;
            border-radius: 99px;
            background: #f0f1f3;
        }

        .progress-bar {
            width: 66%;
            height: 100%;
            border-radius: inherit;
            background: var(--brand);
        }

        .checks {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin-top: 24px;
        }

        .check {
            display: flex;
            align-items: center;
            gap: 9px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.4;
        }

        .check.done {
            color: var(--ink);
        }

        .check-icon {
            display: grid;
            flex: 0 0 auto;
            width: 22px;
            height: 22px;
            place-items: center;
            border-radius: 50%;
            color: var(--brand-dark);
            background: var(--soft);
            font-size: 13px;
            font-weight: 900;
        }

        .footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-top: 42px;
            padding-top: 22px;
            border-top: 1px solid var(--line);
            color: var(--muted);
            font-size: 13px;
        }

        .support {
            color: var(--ink);
            font-weight: 800;
            text-decoration: none;
        }

        .support:hover {
            color: var(--brand-dark);
        }

        @media (max-width: 620px) {
            .page {
                padding: 16px;
            }

            .card {
                border-radius: 22px;
            }

            .topbar {
                align-items: flex-start;
                padding: 18px;
            }

            .status {
                padding: 7px 9px;
                font-size: 10px;
            }

            .content {
                padding: 40px 18px 20px;
            }

            .checks {
                grid-template-columns: 1fr;
                gap: 11px;
            }

            .progress-section {
                margin-top: 38px;
            }

            .footer {
                align-items: flex-start;
                flex-direction: column;
                margin-top: 32px;
            }
        }
    </style>
</head>

<body>
    <main class="page">
        <div class="accent" aria-hidden="true"></div>

        <section class="card" aria-labelledby="maintenance-title">
            <header class="topbar">
                <div class="brand">
                    <span class="brand-mark" aria-hidden="true">+</span>
                    <span>{{ config('app.name', 'Your Website') }}</span>
                </div>
                <div class="status"><span class="status-dot" aria-hidden="true"></span>Maintenance mode</div>
            </header>

            <div class="content">
                <p class="eyebrow"><span class="eyebrow-line" aria-hidden="true"></span> A little tune-up</p>
                <h1 id="maintenance-title">We&apos;ll be back soon.</h1>
                <p class="message">
                    {{ $message ?? 'We are currently improving our website. Thank you for your patience — we will be back online shortly.' }}
                </p>

                <section class="progress-section" aria-label="Maintenance progress">
                    <p class="progress-label">Restoring your experience</p>
                    <p class="progress-value">In progress</p>
                    <div class="progress-track" role="progressbar" aria-label="Maintenance progress" aria-valuemin="0"
                        aria-valuemax="100" aria-valuenow="66">
                        <div class="progress-bar"></div>
                    </div>
                </section>

                <div class="checks">
                    <div class="check done"><span class="check-icon">✓</span><span>Systems backed up</span></div>
                    <div class="check done"><span class="check-icon">✓</span><span>Updates applied</span></div>
                    <div class="check"><span class="check-icon">…</span><span>Final checks running</span></div>
                </div>

                <footer class="footer">
                    <span>Thanks for your patience.</span>
                    {{-- <a class="support" href="mailto:support@example.com">Contact support →</a> --}}
                </footer>
            </div>
        </section>
    </main>
</body>

</html>