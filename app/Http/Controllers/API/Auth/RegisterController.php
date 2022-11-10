<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Support\Str;
class RegisterController extends BaseController
{


    public function index(Request $request)
    {

        $validator = $this->validator($request->all());

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors());
        }

        [$user, $error] = $this->create($request->all());


        if ($user) {
            $token = $user->createToken('Personal Access Token')->plainTextToken;
        }

        if ($user) {
            $respose = [
                'user' => new UserResource($user),
                'token' => $token
            ];
            return $this->sendResponse("Registration success", $respose);
        } else {
            return $this->sendError('Sorry, something went wrong. Please try again later',$error);
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
            'name' => ['required', 'min:2', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => ['min:10', 'max:13'],
            'dob' => ['date','date_format:Y-m-d'],
            'address' => ['string'],
            'password' => ['required', 'string', 'min:4', 'confirmed']
        ]);
    }


    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\Models\User
     */
    protected function create(array $data)
    {
        DB::beginTransaction();
        try {

            $user  = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'dob' => $data['dob'],
                'address' => $data['address'],
                'password' => Hash::make($data['password'])
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return [null, $e];
        }

        DB::commit();
        return [$user, null];
    }

}
