<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mail extends Model
{
    protected $fillable = [
    'accountId',
    'sender',
    'mailId',
    'body',
    'subject',
    'sys_read',
    'user_read',
    'received_date'
];
}
