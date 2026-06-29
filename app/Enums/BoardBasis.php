<?php

namespace App\Enums;

enum BoardBasis: string
{
    case RoomOnly = 'room_only';
    case BedAndBreakfast = 'bed_and_breakfast';
    case HalfBoard = 'half_board';
    case FullBoard = 'full_board';
    case AllInclusive = 'all_inclusive';
}
