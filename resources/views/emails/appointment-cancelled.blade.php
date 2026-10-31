<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Appointment Status Update</title>
</head>
<body style="font-family: sans-serif; color: #18181b; line-height: 1.6;">
    <h2>Appointment Update: {{ ucfirst(str_replace('_', ' ', $appointment->status->value)) }}</h2>
    <p>Hello {{ $appointment->patient->user->name }},</p>
    <p>Your appointment (<strong>{{ $appointment->reference_code }}</strong>) with {{ $appointment->doctor->name }} scheduled for {{ $appointment->starts_at->timezone('Asia/Manila')->format('F j, Y \a\t g:i A') }} is now marked as <strong>{{ str_replace('_', ' ', $appointment->status->value) }}</strong>.</p>
    @if ($reason)
        <p><strong>Reason:</strong> {{ $reason }}</p>
    @endif
    <p>If you would like to book another consultation time, please visit the patient portal.</p>
</body>
</html>

