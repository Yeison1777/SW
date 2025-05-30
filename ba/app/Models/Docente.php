<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Docente extends Model
{
    protected $table = 'docentes';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $fillable = ['id'];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id');
    }
}
