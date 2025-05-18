<?php

namespace App\Http\Controllers;

use App\Models\Administrator;
use App\Models\Game;
use App\Models\Score;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        $user = $request->user(); // user yang sudah login (dari sanctum)

        // Cek apakah username user yang login adalah admin1 atau admin2
        if (!in_array($user->username, ['admin1', 'admin2'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden: You are not allowed to access this resource.'
            ], 403);
        }

       $admin = User::whereIn('username', ['admin1', 'admin2'])
        ->get(['username', 'last_login_at', 'created_at', 'updated_at']);

        return response()->json([
            'totalElements' => $admin->count(),
            'content' => $admin
        ]);
    }

    public function createUser(Request $request)  {
        $user = $request->user(); // user yang sudah login (dari sanctum)

        // Cek apakah username user yang login adalah admin1 atau admin2
        if (!in_array($user->username, ['admin1', 'admin2'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden: You are not allowed to access this resource.'
            ], 403);
        }

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

    public function getAllUser(Request $request)  {
        $user = $request->user(); // user yang sudah login (dari sanctum)

        // Cek apakah username user yang login adalah admin1 atau admin2
        if (!in_array($user->username, ['admin1', 'admin2'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden: You are not allowed to access this resource.'
            ], 403);
        }

        $user = User::get([
            'username',
            'last_login_at',
            'created_at',
            'updated_at',
        ]);

        return response()->json([
            'totalElements' => $user->count(),
            'content' => $user
        ]);

    }

   public function updateUser(Request $request, $id) {
    $user = $request->user(); // user yang sudah login (dari sanctum)

    // Cek apakah username user yang login adalah admin1 atau admin2
    if (!in_array($user->username, ['admin1', 'admin2'])) {
        return response()->json([
            'status' => 'error',
            'message' => 'Forbidden: You are not allowed to access this resource.'
        ], 403);
    }

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
        $user = $request->user(); // user yang sudah login (dari sanctum)

        // Cek apakah username user yang login adalah admin1 atau admin2
        if (!in_array($user->username, ['admin1', 'admin2'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden: You are not allowed to access this resource.'
            ], 403);
        }

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

public function getUserDetails($username)
{
    // Get the requested user
    $user = User::where('username', $username)->first();

    if (!$user) {
        return response()->json([
            'status' => 'not_found',
            'message' => 'User not found'
        ], 404);
    }

    // Get games authored by the user
    $authoredGamesQuery = Game::where('created_by', $user->id);
    
    // If not the user themselves, only show games that have versions
    if (Auth::id() !== $user->id) {
        $authoredGamesQuery->has('versions');
    }

    $authoredGames = $authoredGamesQuery->get(['slug', 'title', 'description']);

    // Get the user's high scores for each game
    $highScores = Score::select([
            'game_versions.game_id',
            'scores.score',
            'scores.created_at'
        ])
        ->join('game_versions', 'scores.game_version_id', '=', 'game_versions.id')
        ->join('games', 'game_versions.game_id', '=', 'games.id')
        ->where('scores.user_id', $user->id)
        ->orderBy('scores.score', 'desc')
        ->get()
        ->groupBy('game_id')
        ->map(function ($scores, $gameId) {
            // Get the highest score for this game
            $highestScore = $scores->first();
            
            // Get game details
            $game = Game::find($gameId, ['slug', 'title', 'description']);
            
            return [
                'game' => [
                    'slug' => $game->slug,
                    'title' => $game->title,
                    'description' => $game->description
                ],
                'score' => $highestScore->score,
                'timestamp' => $highestScore->created_at->toIso8601String()
            ];
        })
        ->values(); // Convert to indexed array

    return response()->json([
        'username' => $user->username,
        'registeredTimestamp' => $user->created_at->toIso8601String(),
        'authoredGames' => $authoredGames,
        'highscores' => $highScores
    ], 200);
}

}
