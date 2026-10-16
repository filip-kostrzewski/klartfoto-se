<!doctype html>
<html lang="sv">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Din fotorapport är klar</title>
</head>
<body style="margin:0; padding:32px 12px; background:#f0f2ec; color:#20372b; font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px; margin:0 auto; border-collapse:collapse;">
        <tr>
            <td style="padding:0 0 14px; color:#526057; font-size:12px; font-weight:bold; letter-spacing:2px;">KLARTFOTO</td>
        </tr>
        <tr>
            <td style="padding:30px 28px; border:1px solid #dce3d8; border-radius:16px; background:#ffffff;">
                <p style="margin:0 0 8px; color:#536b4c; font-size:11px; font-weight:bold; letter-spacing:1.5px;">FOTORAPPORT</p>
                <h1 style="margin:0 0 16px; color:#20372b; font-family:Georgia,'Times New Roman',serif; font-size:30px; font-weight:normal; line-height:1.15;">Din fotorapport är klar</h1>
                <p style="margin:0 0 18px; color:#405046; font-size:15px; line-height:1.65;">Hej {{ $customerName }}! {{ $companyName }} har sammanställt rapporten från besöket på <strong>{{ $address }}</strong> den <strong>{{ $performedOn }}</strong>.</p>

                @if (filled($customerMessage))
                    <div style="margin:0 0 22px; padding:16px; border:1px solid #e1e8dc; border-radius:10px; background:#f7f9f4;">
                        <p style="margin:0 0 7px; color:#34483a; font-size:13px; font-weight:bold;">Meddelande från {{ $companyName }}</p>
                        <p style="margin:0; color:#405046; font-size:14px; line-height:1.6; white-space:pre-line;">{{ $customerMessage }}</p>
                    </div>
                @endif

                <p style="margin:0 0 22px;">
                    <a href="{{ $reportUrl }}" style="display:inline-block; min-height:48px; box-sizing:border-box; padding:15px 21px; border-radius:999px; background:#233d2f; color:#ffffff; font-size:14px; font-weight:bold; line-height:18px; text-decoration:none;">Öppna fotorapporten</a>
                </p>
                <p style="margin:0 0 18px; color:#536057; font-size:13px; line-height:1.6;">Om knappen inte fungerar kan du öppna rapporten här:<br><a href="{{ $reportUrl }}" style="color:#294d37; overflow-wrap:anywhere;">{{ $reportUrl }}</a></p>
                <p style="margin:0 0 18px; color:#405046; font-size:14px; line-height:1.6;">Vänliga hälsningar,<br>{{ $companyName }}</p>
                <p style="margin:0; color:#657168; font-size:11px; line-height:1.5;">Du får det här mejlet eftersom {{ $companyName }} har skickat en rapport från sitt arbete.</p>
            </td>
        </tr>
    </table>
</body>
</html>
