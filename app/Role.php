<?php

namespace App;

enum Role: string
{
    case Administrator = 'administrator';
    case Receptionist = 'receptionist';
    case Practitioner = 'practitioner';
    case Patient = 'patient';
}
