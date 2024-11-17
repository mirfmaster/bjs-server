<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IF_User extends Model
{
    use HasFactory;

    protected $connection = 'if_mysql';

    protected $table = 'users';
}
