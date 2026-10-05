<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

/** Modèle de la base centrale : connexion « central » forcée. */
abstract class CentralModel extends Model
{
    protected $connection = 'central';

    protected $guarded = ['id'];
}
