<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VendorController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $vendors = Vendor::all();
        return (new BaseController)->sendResponse('All vendors', $vendors);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
    
        
        $validator = Validator::make($request->all(), 
        ['name' => 'required|string|unique:vendors', 'logo' => 'sometimes|file']);
        if ($validator->fails()) {
        return (new BaseController)->sendError(null, $validator->errors());
        }
        if($request->hasFile('logo'))
        {
            $request['logo_url'] = $request->file('logo')->store('images/vendors');
        }
        $vendor = Vendor::create($request->all());
        return (new BaseController)->sendResponse(null, $vendor);

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $vendor = Vendor::findOrFail($id);
        return (new BaseController)->sendResponse(null, $vendor);

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), 
        ['name' => 'required_if:logo,null|string|unique:vendors', 'logo' => 'required_if:name,null|sometimes|file']);
        if ($validator->fails()) {
        return (new BaseController)->sendError(null, $validator->errors());
        }
        if($request->hasFile('logo'))
        {
            $request['logo_url'] = $request->file('logo')->store('images/vendors');
        }
        $vendor = Vendor::find($id)->update($request->all());
        return (new BaseController)->sendResponse("vendor updated", $vendor);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $vendor = Vendor::find($id)->delete();
        return (new BaseController)->sendResponse("vendor deleted", $vendor);
    }


}
