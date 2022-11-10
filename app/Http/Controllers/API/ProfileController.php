<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class ProfileController extends BaseController
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            $message = "User not found";
            return $this->sendError($message);
        }

        return $this->sendResponse("User data", new UserResource($user));
    }

    public function update(Request $request)
    {
        $userId = $request->user()->id;
        $validator = $this->profileValidator($request->all(), $userId);

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors());
        }


        $user = User::findOrFail($userId);

        DB::beginTransaction();
        try {
            $user->update($request->all());
        } catch (\Exception $e) {
            DB::rollback();
            return $this->sendError('Sorry, Something went wrong', $e);
        }

        DB::commit();


        return $this->sendResponse("Profile updated", new UserResource($user));
    }


    public function profileValidator($data, $userId)
    {

        return Validator::make($data, [
            'name' => ['sometimes', 'required', 'min:2', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'string', 'max:255', 'unique:users,email,' . $userId],
            'phone' => ['sometimes', 'required', 'min:10', 'max:13'],
            'dob' => ['sometimes', 'required','date','date_format:Y-m-d'],
            'address' => ['sometimes', 'required', 'string']
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return $this->sendError('User not found');
        }

        // Revoke all tokens...
        $user->tokens()->delete();

        return $this->sendResponse("Logged out");
    }
}
