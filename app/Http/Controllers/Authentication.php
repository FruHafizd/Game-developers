<?php

namespace App\Http\Controllers;

use App\Models\Administrator;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class Authentication extends Controller
{
    public function signup(Request $request)  {
        $validator  = Validator::make($request->all(),[
            'username' => 'required|unique:users,username|min:4|max:60',
            'password' => 'required|min:5|max:10'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid field(s) in request',
                'errors' => $validator->errors()
            ],400);
        }

        $user = User::create([
            'username' => $request->username,
            'password' => Hash::make($request->password)
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'User registration successful',
            'data' => [
                'username' => $user->username,
                'token' => $token,
            ]
        ],200);

    }

    public function signin(Request $request)  {

        $request->validate( [
            'username' => 'required',
            'password' => 'required'
        ]);

        // Login Admin
        $admin = Administrator::where('username',$request->username)->first();

        if ($admin && Hash::check($request->password,$admin->password)) {
            $token = $admin->createToken('auth_token')->plainTextToken;
            $admin->last_login_at = now();
            $admin->save();
            return response()->json([
                'status' => 'success',
                'message' => 'Login successful',
                'data' => [
                    'username' => $admin->username,
                    'token' => $token,
                ]
            ],200);

        }

        // Login User
        $user = User::where('username',$request->username)->first();

        if ($user && Hash::check($request->password,$user->password)) {
            $token = $user->createToken('auth_token')->plainTextToken;
            $user->last_login_at = now();
            $user->save();
            return response()->json([
                'status' => 'success',
                'message' => 'Login successful',
                'data' => [
                    'username' => $user->username,
                    'token' => $token,
                ]
            ],200);

        }

        return response()->json([
            'status' => 'invalid',
            'message' => 'Wrong username or password',
        ],401);
    }

    public function signout(Request $request)  {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Signout successful',
        ]);
    }

    public function getAllAdmins(Request $request)  {
        $admin = Administrator::get([
            'username',
            'last_login_at',
            'created_at',
            'updated_at',
        ]);

        return response()->json([
            'totalElements' => $admin->count(),
            'content' => $admin
        ]);

    }

    public function createUser(Request $request)  {
        $validator  = Validator::make($request->all(),[
            'username' => 'required|unique:users,username|min:4|max:60',
            'password' => 'required|min:5|max:10'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid field(s) in request',
                'errors' => $validator->errors()
            ],400);
        }

        $user = User::create([
            'username' => $request->username,
            'password' => Hash::make($request->password)
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'User registration successful',
            'data' => [
                'username' => $user->username,
                'token' => $token,
            ]
        ],201);

    }

    public function getAllUser()  {
        $admin = User::get([
            'username',
            'last_login_at',
            'created_at',
            'updated_at',
        ]);

        return response()->json([
            'totalElements' => $admin->count(),
            'content' => $admin
        ]);

    }

   public function updateUser(Request $request, $id) {
    $user = User::find($id);

    if (!$user) {
        return response()->json([
            'status' => 'not_found',
            'message' => 'Resource not found'
        ], 404);
    }

    $validator = Validator::make($request->all(), [
        'username' => 'sometimes|unique:users,username,'.$id.'|min:4|max:60',
        'password' => 'sometimes|min:5|max:10'
    ]);

    if ($validator->fails()) {
        $errors = $validator->errors();
        $message = $errors->has('username') && str_contains($errors->first('username'), 'taken') 
            ? 'Username already exists' 
            : 'Invalid field(s) in request';

        return response()->json([
            'status' => 'invalid',
            'message' => $message,
            'errors' => $errors
        ], 400);
    }

    // Hanya update field yang diinput
    $updateData = [];
    if ($request->has('username')) {
        $updateData['username'] = $request->username;
    }
    if ($request->has('password')) {
        $updateData['password'] = Hash::make($request->password);
    }
    $user->update($updateData);
    return response()->json([
        'status' => 'success',
        'username' => $user->username,
    ], 200); 
    }

    public function deleteUser(Request $request, $id)  {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'Resource not found'
            ], 403);
        }

        $user->delete();
        return response()->noContent(); // HTTP 204
    }

}
