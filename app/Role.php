<?php

namespace App;

enum Role: string
{
    case Admin = 'admin';
    case Receptionist = 'receptionist';
    case Patient = 'patient';
}
