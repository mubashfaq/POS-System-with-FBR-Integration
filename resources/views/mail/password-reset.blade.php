<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>{{ $mailData['title'] }}</title>

    <style>
        @media only screen and (max-width: 600px) {
            .email-wrapper {
                width: 100% !important;
            }

            .email-content {
                padding: 28px 20px !important;
            }

            .otp-code {
                font-size: 28px !important;
                letter-spacing: 5px !important;
            }

            .mobile-text {
                font-size: 15px !important;
            }
        }
    </style>
</head>

<body style="margin:0;padding:0;background-color:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#334155;">

    <table
        role="presentation"
        width="100%"
        cellpadding="0"
        cellspacing="0"
        border="0"
        style="width:100%;background-color:#f4f7fb;margin:0;padding:0;"
    >
        <tr>
            <td align="center" style="padding:32px 12px;">

                <table
                    role="presentation"
                    cellpadding="0"
                    cellspacing="0"
                    border="0"
                    class="email-wrapper"
                    style="width:100%;max-width:600px;background-color:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 18px rgba(15,23,42,0.08);"
                >
                    <!-- Header -->
                    <tr>
                        <td
                            align="center"
                            style="background-color:#00466a;padding:28px 24px;"
                        >
                            <a
                                href="#"
                                style="color:#ffffff;text-decoration:none;font-size:22px;font-weight:700;letter-spacing:0.2px;"
                            >
                                {{ $mailData['title'] }}
                            </a>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td class="email-content" style="padding:40px 44px;">

                            <p
                                class="mobile-text"
                                style="margin:0 0 20px;font-size:18px;line-height:1.6;color:#0f172a;"
                            >
                                Hello {{ $mailData['name'] }},
                            </p>

                            <p
                                class="mobile-text"
                                style="margin:0 0 20px;font-size:15px;line-height:1.7;color:#475569;"
                            >
                                Thank you for choosing
                                <strong style="color:#00466a;">
                                    {{ $mailData['title'] }}
                                </strong>.
                                Use the one-time password below to complete your password reset.
                            </p>

                            <p
                                class="mobile-text"
                                style="margin:0 0 28px;font-size:15px;line-height:1.7;color:#475569;"
                            >
                                This OTP is valid for
                                <strong>5 minutes</strong>.
                                Please do not share this code with anyone.
                            </p>

                            <!-- OTP -->
                            <table
                                role="presentation"
                                width="100%"
                                cellpadding="0"
                                cellspacing="0"
                                border="0"
                                style="margin:0 0 30px;"
                            >
                                <tr>
                                    <td align="center">
                                        <div
                                            class="otp-code"
                                            style="display:inline-block;background-color:#e8f3f8;color:#00466a;border:1px solid #b9d9e5;border-radius:8px;padding:14px 24px;font-size:32px;font-weight:700;letter-spacing:8px;line-height:1.2;"
                                        >
                                            {{ $mailData['otp'] }}
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <p
                                class="mobile-text"
                                style="margin:0;font-size:15px;line-height:1.7;color:#475569;"
                            >
                                If you did not request a password reset, you can safely ignore this email.
                            </p>

                            <p
                                class="mobile-text"
                                style="margin:28px 0 0;font-size:15px;line-height:1.7;color:#475569;"
                            >
                                Regards,<br />
                                <strong style="color:#00466a;">
                                    Your {{ $mailData['title'] }} Team
                                </strong>
                            </p>

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td
                            align="center"
                            style="border-top:1px solid #e2e8f0;padding:22px 20px;background-color:#f8fafc;"
                        >
                            <p style="margin:0;font-size:13px;line-height:1.5;color:#94a3b8;">
                                © {{ date('Y') }} {{ $mailData['title'] }}. All rights reserved.
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>