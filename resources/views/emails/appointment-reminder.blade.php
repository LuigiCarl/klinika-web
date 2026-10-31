<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upcoming Appointment Reminder</title>
</head>
<body style="font-family: sans-serif; color: #18181b; line-height: 1.6;">
    <h2>Upcoming Appointment Reminder</h2>
    <p>Hello {{ $appointment->patient->user->name }},</p>
    <p>This is a friendly reminder for your upcoming consultation at <strong>Klinika Medical Center</strong>:</p>
    <ul>
        <li><strong>Reference Code:</strong> {{ $appointment->reference_code }}</li>
        <li><strong>Doctor:</strong> {{ $appointment->doctor->name }} ({{ $appointment->doctor->specialty->name }})</li>
        <li><strong>Scheduled Date & Time:</strong> {{ $appointment->starts_at->timezone('Asia/Manila')->format('F j, Y \a\t g:i A') }} (Asia/Manila)</li>
    </ul>
    <p>Please arrive 10 minutes before your scheduled appointment time.</p>
</body>
</html>

