<?php

namespace App\Classes\Outlook;

use App\Classes\BaseAccount;
use App\Http\Controllers\API\BaseController;
use App\Models\Account;
use App\Models\Mail;
use Beta\Microsoft\Graph\Model\User;
use Microsoft\Graph\Graph;
use Beta\Microsoft\Graph\Model\Message;

class Outlook extends BaseAccount
{
  static function client()
  {
    $client = new \League\OAuth2\Client\Provider\GenericProvider([
      'clientId'                => config('outlook.appId'),
      'clientSecret'            => config('outlook.appSecret'),
      'redirectUri'             => config('outlook.redirectUri'),
      'urlAuthorize'            => config('outlook.authority') . config('outlook.authorizeEndpoint'),
      'urlAccessToken'          => config('outlook.authority') . config('outlook.tokenEndpoint'),
      'urlResourceOwnerDetails' => '',
      'scopes'                  => config('outlook.scopes'),

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

  static function saveToken($code, $state)
  {
    if (isset($code) && $code != "") {
      $oauthClient = self::client($state->key);

      try {
        // Make the token request
        $accessToken = $oauthClient->getAccessToken('authorization_code', [
          'code' => $code
        ]);
        $graph = new Graph();
        $graph->setAccessToken($accessToken->getToken());
        $user = $graph->createRequest('GET', '/me?$select=displayName,mail,mailboxSettings,userPrincipalName')
          ->setReturnType(User::class)
          ->execute();

        $check = self::checkIfExist($user->getUserPrincipalName(), 'outlook', $state->userId);
        if ($check) {
          return (new BaseController)->sendError('Outlook account already linked');
        }
       
        $account = Account::create([
          'email' => $user->getUserPrincipalName(),
          'userId' => $state->userId,
          'access_token' => $accessToken->getToken(),
          'refresh_token' => $accessToken->getRefreshToken(),
          'expires_in' => $accessToken->getExpires(),
          'provider' => 'outlook'
        ]);
        self::saveMails($account);
        return (new BaseController)->sendResponse('Outlook account linked successfully');
      } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
        return (new BaseController)->sendError('something went wrong, Please try again later');
      }
    }
    return (new BaseController)->sendError('Code is not found or wrong code used');
  }

  static public function saveMails($account)
  {
    $graph = new Graph();
    $graph->setAccessToken($account->access_token);
    $mails = $graph->createRequest('GET', '/me/mailfolders/inbox/messages?$select=subject,from,receivedDateTime,body&$top=25&$orderby=receivedDateTime%20DESC')
      ->setReturnType(Message::class)
      ->execute();
    foreach ($mails as $key => $data) {
      if (!Mail::where('mailId', $data->getId())->first()) {
        $getEmailAddress = $data->getFrom()->getProperties()['emailAddress'];
        $mail = new Mail;
        $mail->accountId = $account->id;
        $mail->body = $data->getBody()->getContent();
        $mail->subject = $data->getSubject();
        $mail->mailId = $data->getId();
        $mail->received_date = $data->getReceivedDateTime();
        $mail->sender = $getEmailAddress['address'] ?? $getEmailAddress['name'] ?? "unknow";
        $mail->save();
      }
    }
    return true;
  }
}
