<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['hbx_hotel_id', 'language', 'name', 'description', 'address', 'seo_title', 'seo_description'])]
class HbxHotelTranslation extends Model {}
