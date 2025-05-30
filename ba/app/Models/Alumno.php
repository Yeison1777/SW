<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alumno extends Model
{
    protected $table = 'alumnos';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $fillable = ['id'];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id');
    }
}
