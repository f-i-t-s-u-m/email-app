<?php

namespace App\Classes\Imap;

use App\Classes\BaseAccount;
use App\Http\Controllers\API\BaseController;
use App\Models\Account;
use App\Models\Mail;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class Imap extends BaseAccount
{
     static function client($email, $password)
     {
          $url = explode("@", $email);

          $client = \Webklex\IMAP\Facades\Client::make([
               'host'          => 'imap.' . $url[1],
               'port'          => 993,
               'encryption'    => 'ssl',
               'validate_cert' => true,
               'username'      => $email,
               'password'      => $password,
               'protocol'      => 'imap'
          ]);
          return $client;
     }

     static function saveLogin($email, $password)
     {
          $client = self::client($email, $password);
          try {
               $client->connect();
               if ($client->isConnected()) {

                    $check = self::checkIfExist($email, 'other', Auth::id(), $password);
                    if ($check) {
                         return (new BaseController)->sendError('Other account already linked');
                    }

                    $account = Account::updateOrCreate(
                         [
                              "email" => $email,
                              "provider" => 'other',
                              "userId" => Auth::id()
                         ],
                         [
                              "access_token" => Crypt::encryptString($password),
                              "protocol" => 'imap' 
                         ]
                    );
                    self::saveMails($account);
                    if($account->exists) {
                    return (new BaseController)->sendResponse('Other account updated successfully');
                    } else return (new BaseController)->sendResponse('Other account linked successfully');
               } else {
                    return (new BaseController)->sendError('Login failed, Wrong credentials. Please check again');
               }
          } catch (Exception  $e) {
               return (new BaseController)->sendError('Login failed, Wrong credentials. Please check again');
          }

          return (new BaseController)->sendResponse('Other account linked successfully');
     }

     public static function saveMails($account)
     {
          $password = Crypt::decryptString($account->access_token);
          $client = self::client($account->email, $password);
          //Connect to the IMAP Server
          $client->connect();

          //Get all Mailboxes
          /** @var \Webklex\PHPIMAP\Support\FolderCollection $folders */
          $folders = $client->getFolders();

          //Loop through every Mailbox
          /** @var \Webklex\PHPIMAP\Folder $folder */
          foreach ($folders as $folder) {

               //Get all Messages of the current Mailbox $folder
               /** @var \Webklex\PHPIMAP\Support\MessageCollection $messages */
               $messages = $folder->messages()->all()->get();
               /** @var \Webklex\PHPIMAP\Message $message */
               foreach ($messages as $message) {

                    $data['mailId'] = (string)Str::orderedUuid();
                    $data['received_date'] = (new Carbon((string)$message->getDate()))->toDateTimeString();
                    $data['sender'] = trim(explode("<", (string)$message->getFrom())[1], '>');
                    $data['subject'] = (string)$message->getSubject();
                    $data['body'] = $message->getHTMLBody();
                    $data['accountId'] = $account->id;


                    if (!Mail::where('mailId', $data['mailId'])
                         ->where('accountId', $account->id)
                         ->first())  Mail::create($data);
               }
          }
         
          return true;
     }
}
