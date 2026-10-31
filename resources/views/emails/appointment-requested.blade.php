<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Appointment Request Received</title>
</head>
<body style="font-family: sans-serif; color: #18181b; line-height: 1.6;">
    <h2>Appointment Request Received</h2>
    <p>Hello {{ $appointment->patient->user->name }},</p>
    <p>We have received your appointment request at <strong>Klinika Medical Center</strong>. Our reception desk is reviewing your request.</p>
    <ul>
        <li><strong>Reference Code:</strong> {{ $appointment->reference_code }}</li>
        <li><strong>Doctor:</strong> {{ $appointment->doctor->name }} ({{ $appointment->doctor->specialty->name }})</li>
        <li><strong>Requested Date & Time:</strong> {{ $appointment->starts_at->timezone('Asia/Manila')->format('F j, Y \a\t g:i A') }} (Asia/Manila)</li>
        <li><strong>Duration:</strong> {{ $appointment->duration_minutes }} minutes</li>
    </ul>
    <p>You will receive a confirmation email with a calendar invite once approved.</p>
</body>
</html>

