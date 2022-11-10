<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\UserResource;
use App\Models\User;

class LoginController extends BaseController
{


    public function index(Request $request)
    {

        $validator = $this->validator($request->all());

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors());
        }


        $user = $this->verifyLogin($request);

        if ($user) {
            $token = $user->createToken('Personal Access Token')->plainTextToken;
        }

        if ($user) {
            $respose = [
                'user' => new UserResource($user),
                'token' => $token
            ];
            return $this->sendResponse("Login success!", $respose);
        } else {
            return $this->sendError('These credentials do not match our records');
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
            'email'      => ['required', 'email', 'max:255'],
            'password'      => ['required']
        ]);
    }


    protected function verifyLogin($request)
    {

        $user = User::where('email', $request->email)->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            return null;
        }

        return $user;
    }
}
