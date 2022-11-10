<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Exception;
use App\Classes\Gmail\Gmail;
use App\Classes\Imap\Imap;
use App\Classes\Outlook\Outlook;
use App\Classes\Yahoo\Yahoo;
use App\Http\Controllers\ParserController;
use App\Models\Account;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 *
 */
class AccountController extends Controller
{
    /**
     * @throws Exception
     */

    public function getGmailAccessToken(): JsonResponse
    {

        $mesage = 'Please click this link and give a permission for this app.';
        $data = [
            'gmail_link' => Gmail::getUrl(),
            'outlook_link' => Outlook::getUrl() ,
            'others_link' => route('other.providers')
        ];

        return (new BaseController)->sendResponse($mesage, $data);
    }

    /**
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {

        $authId = Auth::id();
        $account = Account::with('mails')->where('userId', $authId)->get();
        return (new BaseController)->sendResponse(null, $account);

    }

    public function fetchNewMails()
    {
         $authId = Auth::id();
         $accounts = Account::where('userId', $authId)->get();
         foreach ($accounts as $account) {
             $account->saveMails();
         }

         (new ParserController)->mailScraper();
         return (new BaseController)->sendResponse("your mail accounts synced successfully", $accounts);
    }

    public function show($id) {
        $authId = Auth::id();
        $account = Account::where('userId', $authId)
            ->where('id', $id)
            ->first();
        return (new BaseController)->sendResponse("Linked email details", $account);

    }

    public function userEmails()
    {
        $account = Account::where('userId', auth()->id())->get();
        return (new BaseController)->sendResponse("Linked emails", $account);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function redirectFromGmail(Request $request): JsonResponse
    {
        $providedState = $request->query('state');
        if (!isset($providedState)) {
            return redirect()->route('verify');
        }
        if($state = Gmail::checkState($providedState))
        {
            $isSaved = Gmail::saveToken($request->query('code'), $state);
            (new ParserController)->mailScraper($state->userId);
            return $isSaved;
        }
        else {

            return (new BaseController)->sendError("we can\'t verify your account, please try again");

        }
    }

    public function redirectFromOutlook(Request $request)
    {

        $providedState = $request->query('state');
        if (!isset($providedState)) {
            return redirect()->route('verify');
        }
        if($state = Outlook::checkState($providedState))
        {
            $isSaved = Outlook::saveToken($request->query('code'), $state);
            (new ParserController)->mailScraper($state->userId); 
            return $isSaved;
        }
        else {
            return (new BaseController)->sendError("we can\'t verify your account, please try again");
        }
    }

    public function other(Request $request)
    {
        // login gmx web.de
        $isSaved = Imap::saveLogin($request->email, $request->password);
        return $isSaved;
    }


    public function remove($id): JsonResponse
    {

        $account = Account::query()
            ->where('userId', Auth::id())
            ->where('id', $id)
            ->delete();

        if($account == 0){
            return (new BaseController)->sendError("Account not found.", "account not found on your linked emails list");
        }

        return (new BaseController)->sendResponse("Account Successfully Removed from the system.", $account);
    }

}
