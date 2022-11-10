<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends BaseController
{

    public function index(Request $request)
    {
        $validator = $this->validator($request->all());

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors());
        } else {

            $response =  Password::sendResetLink($request->only('email'));

            if ($response == Password::RESET_LINK_SENT) {
                $message = "Password reset link sent to your email, please check!";
                return $this->sendResponse($message);
            } else {
                $message = "Email could not be sent to this email address";
                return $this->sendError($message);
            }
        }
    }


    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'email'      => ['required', 'email', 'max:255']
        ]);
    }
}
