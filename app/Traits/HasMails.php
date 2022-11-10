<?php

namespace App\Traits;

use App\Classes\Gmail\Gmail;
use App\Classes\Imap\Imap;
use App\Classes\Outlook\Outlook;



trait HasMails
{
     public function initializeHasMails()
     {
          $this->append('provider_image');
     }
     public function getProviderImageAttribute()
     {
          if ($this->provider == 'gmail') {
               return asset('images/google_logo.png');
          } else if ($this->provider == 'outlook') {
               return asset('images/outlook_logo.png');
          }
     }
     public function refreshToken()
     {
          if ($this->expires_in <= time() + 300) {
               if ($this->provider == 'outlook') {

                    $oauthClient = Outlook::client();

                    try {
                         // Make the token request
                         $accessToken = $oauthClient->getAccessToken('refresh_token', [
                              'refresh_token' => $this->refresh_token
                         ]);

                         $this->update([
                              'access_token' => $accessToken->getToken(),
                              'refresh_token' => $accessToken->getRefreshToken(),
                              'expires_in' => $accessToken->getExpires()
                         ]);
                    } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
                         return  json_encode($e->getResponseBody());
                    }
               } else if ($this->provider == 'gmail') {
                    $client = Gmail::client('nokey');
                    $accessToken = $client->fetchAccessTokenWithRefreshToken($this->refresh_token);
                    $this->update($accessToken);
               }
          }
     }
     public function saveMails()
     {
          $this->refreshToken();
          $provider = $this->provider;
          if ($provider == 'outlook') {

               $saveMails = Outlook::saveMails($this);
               return $saveMails ? $saveMails : false; 
               
          } else if ($provider == 'gmail') {
               
               $saveMails = Gmail::saveMails($this);
               return $saveMails ? $saveMails : false; 
              
          }

          else if($provider == 'other')
          {
               $saveMails = Imap::saveMails($this);
               return $saveMails ? $saveMails : false; 
          }
     }
}
