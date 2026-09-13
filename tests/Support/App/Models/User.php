<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Lineage\Traits\HasLineageWallets;

class User extends Model
{
    use HasLineageWallets;

    protected $fillable = [
        'name',
        'email',
    ];
}
