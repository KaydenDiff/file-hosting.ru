<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'extension',
        'path',
        'file_id',

    ];
    public function rights()
    {
        return $this->hasMany(Right::class);
    }

}
