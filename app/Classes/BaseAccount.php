<?php

namespace App\Classes;

use App\Models\Account;
use App\Models\State;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class BaseAccount
{

     static function checkIfExist($email, $provider, $userId, $password = null)
     {
          $user = Account::where('email', $email)
               ->where('provider', $provider)
               ->where('userId', $userId)
               ->first();
          if($password && $user)
          {
               return Crypt::decryptString($user->access_token) === $password;
          }
          else if ($user) {
               return true;
          }
          return false;
     }

     static function checkState($state)
     {
          $state = State::where('key', $state)->first();
          return $state;
     }

     static function saveState($state)
     {
          State::create([
               'userId' => auth()->user()->id,
               'key' => $state
          ]);
          return true;
     }

     static function genKey()
     {
          $key = Str::random(10);
          $state = State::where('key', $key)->first();
          if (!$state) {
               return $key;
          }

          self::genKey();
     }
}
