<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpcionVotacion extends Model
{
    protected $table = 'opciones_votacion';

    protected $fillable = ['votacion_id', 'texto', 'orden'];
}
