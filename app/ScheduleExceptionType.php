<?php

namespace App;

enum ScheduleExceptionType: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';
}
