<?php

namespace App\Classes\Gmail;

use App\Classes\BaseAccount;
use App\Http\Controllers\API\BaseController;
use App\Models\Account;
use App\Models\Mail;
use Google_Client;
use Illuminate\Support\Carbon;
use ZBateson\MailMimeParser\MailMimeParser;

class Gmail extends BaseAccount
{
     /**
      * @var $key
      */
     private static $key;


     static function client($key = null)
     {
          $client = new Google_Client();
          $client->setClientId(config('gmail.client_id'));
          $client->setClientSecret(config('gmail.client_secret'));
          $client->setRedirectUri(config('gmail.redirect_url'));
          $client->addScope(config('gmail.scopes'));
          $client->setApplicationName('gmail API PHP Quickstart');
          $client->setScopes(['https://www.googleapis.com/auth/gmail.readonly', 'https://www.googleapis.com/auth/gmail.modify']);
          $client->setAccessType(config('gmail.access_type'));
          $client->setPrompt('select_account consent');
          $client->setState($key ?? self::$key = self::genKey());
          return $client;
     }
     static function getUrl()
     {
          $url = self::client()->createAuthUrl();
          self::saveState(self::$key);
          return $url;
     }

     static function saveToken($code, $state)
     {

          $client = self::client($state->key);
          $accessToken = $client->fetchAccessTokenWithAuthCode($code);
          $client->setAccessToken($accessToken['access_token']);
          $service = new \Google_Service_Gmail($client);
          $user = 'me';
          $email = $service->users->getProfile($user)->emailAddress;

          $check = self::checkIfExist($email, 'gmail', $state->userId);
          if ($check) {
               return (new BaseController)->sendError('Gmail accounted already linked');
          }
          $accessToken['email'] = $email;
          $accessToken['provider'] = 'gmail';
          $accessToken['userId'] = $state->userId;
          $accessToken['state'] = $state->key;
          $account = Account::create($accessToken);
          self::saveMails($account);
          return (new BaseController)->sendResponse('Gmail account linked successfully');
     }
      public static function saveMails($account)
     {
          $client = Gmail::client();
          $client->setAccessToken($account->access_token);
          $service = new \Google_Service_Gmail($client);
          $serviceMessage = $service->users_messages;
          $user = 'me';
          $lists = $service->users_messages->listUsersMessages($user)->messages;
          $results = collect($lists)->map(function ($list) use ($serviceMessage, $user, $account) {
               $result = $serviceMessage->get($user, $list->id, ['format' => 'raw']);
               $base64 = strtr($result->raw, '-_', '+/');
               $rawMail = base64_decode($base64);
               $message = (new MailMimeParser)->parse($rawMail, true);
               $data['received_date'] = (new Carbon($message->getHeaderValue('Date')))->toDateTimeString();
               $data['sender'] = $message->getHeaderValue('From');
               $data['subject'] = $message->getHeaderValue('Subject');
               $data['accountId'] = $account->id;
               $data['mailId'] = $result->id;
               $data['body'] = $message->getHtmlContent();
               return $data;
          });
          foreach ($results as $data) {
               $mail = new Mail();
               if (!Mail::where('mailId', $data['mailId'])->first())  $mail->create($data);
          }

          return true;
     }
}
