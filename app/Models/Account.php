<?php

namespace App\Models;
use App\Traits\HasMails;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;


class Account extends Model
{
  use HasMails;
  protected $hidden = ['access_token',
  'refresh_token'];
  protected $appends = ['created_at_formatted'];
    protected $fillable = [
    'email',
    'userId',
    'provider',
    'access_token',
    'expires_in',
    'refresh_token',
    'protocol',
    'created'];
  
    public function mails()
    {
      return $this->hasMany(Mail::class, 'accountId');
    }

    public function getCreatedAtFormattedAttribute()
    {
      return Carbon::parse($this->created_at)->diffForHumans();
    }
}
