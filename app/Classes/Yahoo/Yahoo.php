<?php

namespace App\Classes\Yahoo;

use App\Classes\BaseAccount;
use Exception;

class Yahoo extends BaseAccount
{

     static function client()
     {
          $client = new \League\OAuth2\Client\Provider\GenericProvider([
               'clientId'                => config('yahoo.clientId'),
               'clientSecret'            => config('yahoo.appSecret'),
               'redirectUri'             => config('yahoo.redirectUri'),
               'urlAuthorize'            => config('yahoo.authority') . config('yahoo.authorizeEndpoint'),
               'urlAccessToken'          => config('yahoo.authority') . config('yahoo.tokenEndpoint'),
               'urlResourceOwnerDetails' => '',
               'scopes'                  => config('yahoo.scopes'),

          ]);

          return $client;
     }

     static function getUrl()
     {
          $oauthClient = self::client();
          $authUrl = $oauthClient->getAuthorizationUrl();
          self::saveState($oauthClient->getState());
          return $authUrl;
     }

     static function saveToken($code)
     {
        
               $oauthClient = self::client();
                    $accessToken = $oauthClient->getAccessToken('authorization_code', [
                         'code' => $code
                    ]);
            dd($accessToken);
        
     }
}
