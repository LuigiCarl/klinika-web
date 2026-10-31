<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Appointment Confirmed</title>
</head>
<body style="font-family: sans-serif; color: #18181b; line-height: 1.6;">
    <h2>Your Appointment is Confirmed</h2>
    <p>Hello {{ $appointment->patient->user->name }},</p>
    <p>Your appointment at <strong>Klinika Medical Center</strong> has been approved and confirmed.</p>
    <ul>
        <li><strong>Reference Code:</strong> {{ $appointment->reference_code }}</li>
        <li><strong>Doctor:</strong> {{ $appointment->doctor->name }} ({{ $appointment->doctor->specialty->name }})</li>
        <li><strong>Date & Time:</strong> {{ $appointment->starts_at->timezone('Asia/Manila')->format('F j, Y \a\t g:i A') }} (Asia/Manila)</li>
        <li><strong>Duration:</strong> {{ $appointment->duration_minutes }} minutes</li>
    </ul>
    <p>An iCalendar (<code>.ics</code>) invitation is attached to this email so you can add it directly to your calendar.</p>
</body>
</html>

