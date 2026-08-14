<!doctype html>
<html lang="it">
<head><meta charset="utf-8"></head>
<body style="margin:0;background:#f4f6f8;font-family:Arial,sans-serif;color:#172033">
<div style="max-width:680px;margin:0 auto;padding:32px 20px">
    <div style="font-weight:700;font-size:18px;margin-bottom:16px">Commerciale AI</div>
    <div style="background:#fff;border:1px solid #e3e8ef;border-radius:12px;padding:28px;line-height:1.6">
        <div style="display:inline-block;padding:5px 9px;border-radius:20px;background:#fff3d6;color:#8a4b08;font-size:12px;font-weight:bold">INTERVENTO RICHIESTO</div>
        <h1 style="font-size:23px;line-height:1.25;margin:18px 0 8px">La conversazione richiede un commerciale</h1>
        <p>{{ $commercialNotification->message }}</p>
        <table style="width:100%;border-collapse:collapse;margin:20px 0">
            <tr><td style="padding:8px 0;color:#667085">Lead</td><td style="padding:8px 0;font-weight:bold">{{ $commercialNotification->lead?->name }}</td></tr>
            <tr><td style="padding:8px 0;color:#667085">Email</td><td style="padding:8px 0">{{ $commercialNotification->lead?->email }}</td></tr>
            <tr><td style="padding:8px 0;color:#667085">Oggetto ricevuto</td><td style="padding:8px 0">{{ data_get($commercialNotification->data, 'subject') }}</td></tr>
        </table>
        <a href="{{ route('notifications.open', $commercialNotification) }}" style="display:inline-block;background:#3157d5;color:white;text-decoration:none;padding:11px 17px;border-radius:8px;font-weight:bold">Apri il lead</a>
    </div>
</div>
</body>
</html>

