<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\Score;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class GamesController extends Controller
{

public function listGames(Request $request)
{
    $page = $request->input('page', 0);
    $size = $request->input('size', 10);
    $sortBy = $request->input('sortBy', 'title');
    $sortDir = $request->input('sortDir', 'asc');

    // Validate sort parameters
    $validSortFields = ['title', 'popular', 'uploaddate'];
    $validSortDirs = ['asc', 'desc'];
    
    if (!in_array($sortBy, $validSortFields)) {
        $sortBy = 'title';
    }
    
    if (!in_array($sortDir, $validSortDirs)) {
        $sortDir = 'asc';
    }

    $query = Game::with(['creator', 'versions']);
    
     // Terapkan pengurutan berdasarkan field yang diminta
    if ($sortBy === 'title') {
        $query->orderBy('title', $sortDir);
    } elseif ($sortBy === 'popular') {
        // Untuk pengurutan berdasarkan popularitas, gunakan subquery
        $query->select('games.*')
              ->selectRaw('(SELECT COUNT(*) FROM scores JOIN game_versions ON scores.game_version_id = game_versions.id WHERE game_versions.game_id = games.id) as score_count')
              ->orderBy('score_count', $sortDir);
    } elseif ($sortBy === 'uploaddate') {
        // Untuk pengurutan berdasarkan tanggal upload
        $query->select('games.*')
              ->selectRaw('(SELECT MAX(created_at) FROM game_versions WHERE game_versions.game_id = games.id) as latest_upload')
              ->orderBy('latest_upload', $sortDir);
    }

    $totalGames = $query->count();
    
    $games = $query->skip($page * $size)
                   ->take($size)
                   ->get();

    // Format response
    $content = [];
    foreach ($games as $game) {
        $latestVersion = $game->versions->sortByDesc('created_at')->first();

        $content[] = [
            'slug' => $game->slug,
            'title' => $game->title,
            'description' => $game->description,
            'thumbnail' => $latestVersion ? '/games/' . $game->slug . '/'.$latestVersion->version.'/thumbnail.png' : null,
            'uploadTimestamp' => $latestVersion ? $latestVersion->created_at->toIso8601String() : null,
            'author' => $game->creator->username,
            'scoreCount' => Score::whereIn('game_version_id', $game->versions->pluck('id'))->count()
        ];
    }

    return response()->json([
        'page' => (int)$page,
        'size' => count($content),
        'totalElements' => $totalGames,
        'content' => $content
    ]);
}


public function createGame(Request $request)  {
    $validator = Validator::make($request->all(),[
        'title' => 'required|min:3|max:60',
        'description' => 'required|min:0|max:200'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => 'invalid',
            'errors' => $validator->errors()
        ], 400);
    }
    
    $slug = Str::slug($request->title);
    if (Game::where('slug',$slug)->exists()) {
        return response()->json([
            'status' => 'invalid',
            'message' => 'Game title already exists'
        ],400);
    }

    $game = Game::create([
        'title' => $request->title,
        'slug' => $slug,
        'description' => $request->description,
        'created_by' => Auth::id() 
    ]);

    return response()->json([
        'status' => 'success',
        'slug' => $game->slug
    ],201);

}   

public function getDetailGame(Request $request,$slug)
{
    $game = Game::with(['creator','versions'])->where('slug',$slug)->first();

    if (!$game) {
    return response()->json([
            'status' => 'not_found',
            'message' => 'Resource not found'
        ], 404);
    }

    $latestVersion = $game->versions->sortByDesc('created_at')->first();
    return response()->json([
            'slug' => $game->slug,
            'title' => $game->title,
            'description' => $game->description,
            'thumbnail' => $latestVersion ? '/games/' . $game->slug . '/'.$latestVersion->version.'/thumbnail.png' : null,
            'uploadTimestamp' => $latestVersion ? $latestVersion->created_at->toIso8601String() : null,
            'author' => $game->creator->username,
            'scoreCount' => Score::whereIn('game_version_id', $game->versions->pluck('id'))->count(),
            'gamePath' => $latestVersion ? '/games/'.$game->slug.'/'.$latestVersion->id.'/' : null
    ]);

}

public function uploadGameVersion(Request $request,$slug)  {
    $game = Game::where('slug',$slug)->first();

    if (!$game) {
        return response()->json([
            'status' => 'not_found',
            'message' => 'Game Not found'
        ],404);
    }

    if ($game->created_by != Auth::id()) {
        return response()->json([
            'status' => 'forbidden',
            'message' => 'User is not the author of this game'
        ],403);
    }

    // 4. Validasi file
    $request->validate([
        'zipfile' => 'required|file|mimes:zip|max:10240', // Max 10MB
        'thumbnail' => 'nullable|image|mimes:png,jpg|max:2048' // Max 2MB
    ]);

    // Tentukan versi baru (increment dari versi terakhir)
    $latestVersion = $game->versions()->orderByDesc('version')->first();
    $newVersion = $latestVersion ? (int)$latestVersion->version + 1 : 1;
    $versionFolder = "v{$newVersion}";

    //Path penyimpanan
    $basePath = "games/{$game->slug}/{$versionFolder}";

    try {
        //  Simpan file game (zip)
        $zipPath = $request->file('zipfile')->storeAs(
            $basePath, 
            'game.zip', 
            'public'
        );

        //  Simpan thumbnail jika ada
        $thumbnailPath = null;
        if ($request->hasFile('thumbnail')) {
            $thumbnailPath = $request->file('thumbnail')->storeAs(
                $basePath,
                'thumbnail.png', // Selalu simpan sebagai thumbnail.png
                'public'
            );
        }

        //  Simpan record versi baru
        $gameVersion = new GameVersion([
            'version' => $newVersion,
            'storage_path' => $basePath,
            'game_id' => $game->id,
            'thumbnail_path' => $thumbnailPath ? "{$basePath}/thumbnail.png" : null
        ]);
        $gameVersion->save();

        return response()->json([
            'status' => 'success',
            'version' => $newVersion,
            'game_path' => Storage::url("{$basePath}/game.zip"),
            'thumbnail_path' => $thumbnailPath ? Storage::url($thumbnailPath) : null
        ], 201);
        
    } catch (\Exception $e) {
        // Hapus file yang sudah terupload jika ada error
        Storage::deleteDirectory("public/{$basePath}");
        return response()->json([
            'status' => 'Internal Server Error',
            'message' => 'Failed to process upload'
        ],500);
    }

}

public function updateGame(Request $request, $slug)  {
    $game = Game::where('slug',$slug)->first();

    if (!$game) {
        return response()->json([
            'status' => 'not_found',
            'message' => 'Game Not found'
        ],404);
    }

    if ($game->created_by != Auth::id()) {
        return response()->json([
            'status' => 'forbidden',
            'message' => 'You are not the game author'
        ],403);
    }

    $validator = Validator::make($request->all(),[
        'title' => 'sometimes|min:3|max:60',
        'description' => 'sometimes|min:0|max:200'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => 'invalid',
            'message' => 'Invalid field(s) in request',
            'errors' =>  $validator->errors()
        ], 400);
    }

      // Hanya update field yang diinput
    $updateData = [];
    if ($request->has('title')) {
        $updateData['title'] = $request->title;
    }
    if ($request->has('description')) {
        $updateData['description'] = $request->description;
    }
    $game->update($updateData);
    return response()->json([
        'status' => 'success',
        'title' => $game->title,
        'description' => $game->description,
    ], 200); 

}

public function deleteGame($slug)  {
    $game = Game::where('slug',$slug)->first();

    if (!$game) {
        return response()->json([
            'status' => 'not_found',
            'message' => 'Game Not found'
        ],404);
    }

    if ($game->created_by != Auth::id()) {
        return response()->json([
            'status' => 'forbidden',
            'message' => 'You are not the game author'
        ],403);
    }

    $game->versions()->delete(); 
    $game->scores()->delete();
    $game->delete();

    return response(null,204);
}


}
