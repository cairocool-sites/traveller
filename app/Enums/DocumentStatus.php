<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Issued = 'issued';
    case Revoked = 'revoked';
}
