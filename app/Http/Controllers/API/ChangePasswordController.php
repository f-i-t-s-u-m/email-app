<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class ChangePasswordController extends BaseController
{
    public function index(Request $request)
    {
        $user = $request->user();
        $oldPassword = $request->old_password;
        $newPassword = $request->new_password;
        $validator = $this->validator($request->all());

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors());
        } else {


            try {

                if (Hash::check($oldPassword, $user->password)) {


                    DB::beginTransaction();
                    try {
                        User::where('id', $user->id)->update(['password' => Hash::make($newPassword)]);
                    } catch (\Exception $e) {
                        DB::rollback();
                        return $this->sendError('Sorry, Something went wrong', $e);
                    }

                    DB::commit();

                    $respose = "Password successfully updated";
                    return $this->sendResponse($respose);
                } else {
                    return $this->sendError("The old password you have entered is incorrect");
                }
            } catch (\Exception $ex) {

                return $this->sendError('Sorry, Something went wrong', $ex->getMessage());
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
            'old_password' => 'required',
            'new_password' => 'required|min:6',
            'confirm_password' => 'required|same:new_password',
        ]);
    }
}
