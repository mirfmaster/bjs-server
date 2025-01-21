<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Worker extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'username',
        'password',
        'status',
        'followers_count',
        'following_count',
        'media_count',
        'pk_id',
        'is_max_following_error',
        'is_probably_bot',
        'is_verified_email',
        'has_profile_picture',
        'last_access',
        'code',
        'is_verified',
        'on_work',
        'last_work',
        'data',
        'secret_key_2fa',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    // protected $casts = [
    //     'followers_count' => 'integer',
    //     'following_count' => 'integer',
    //     'media_count' => 'integer',
    //     'is_max_following_error' => 'boolean',
    //     'is_probably_bot' => 'boolean',
    //     'is_verified_email' => 'boolean',
    //     'has_profile_picture' => 'boolean',
    //     'last_access' => 'datetime',
    //     'is_verified' => 'boolean',
    //     'on_work' => 'boolean',
    //     'last_work' => 'datetime',
    // ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
    ];
}
