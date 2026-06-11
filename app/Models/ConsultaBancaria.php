<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory; // Added this line

class ConsultaBancaria extends Model
{
    use HasFactory; // Added this line

    protected $guarded = []; // Added this line

    public function user() // Added this method
    {
        return $this->belongsTo(User::class);
    }
}
