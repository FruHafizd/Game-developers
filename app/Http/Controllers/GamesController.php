<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\Score;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            'thumbnail' => $latestVersion ? '/games/' . $game->slug . '/v'.$latestVersion->version.'/thumbnail.png' : null,
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
            'thumbnail' => $latestVersion ? '/games/' . $game->slug . '/v'.$latestVersion->version.'/thumbnail.png' : null,
            'zip' => $latestVersion ? '/games/' . $game->slug . '/v'.$latestVersion->version.'/game.zip' : null,
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
            'version' => "v{$newVersion}",
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

public function serveGameFile($slug, $version, $filename = null)
{
        // Default file adalah 'game.zip' jika tidak ada parameter filename
        $targetFile = $filename ?? 'game.zip';
        $path = "games/{$slug}/v{$version}/{$targetFile}";

        // Cek apakah file ada di storage
        if (!Storage::disk('public')->exists($path)) {
            abort(404, 'File not found');
        }

        // Dapatkan full path fisik
        $fullPath = Storage::disk('public')->path($path);

        // Tentukan Content-Type berdasarkan ekstensi file
        $mimeTypes = [
            'zip' => 'application/zip',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'js' => 'application/javascript',
            'html' => 'text/html',
            'css' => 'text/css'
        ];

        $extension = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
        $contentType = $mimeTypes[$extension] ?? 'application/octet-stream';

        // Return file response dengan header yang sesuai
        return response()->file($fullPath, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=31536000' // Cache 1 tahun
        ]);
}

public function getGameScores($slug)
{
        // Cari game berdasarkan slug
        $game = Game::where('slug', $slug)->first();

        if (!$game) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'Game tidak ditemukan'
            ], 404);
        }

        // Ambil semua versi game ini
        $gameVersionIds = GameVersion::where('game_id', $game->id)
            ->pluck('id')
            ->toArray();

        if (empty($gameVersionIds)) {
            return response()->json([
                'scores' => []
            ], 200);
        }

        // Query untuk mendapatkan skor tertinggi untuk setiap user
        $scores = DB::table('users')
            ->join('scores', 'users.id', '=', 'scores.user_id')
            ->join('game_versions', 'scores.game_version_id', '=', 'game_versions.id')
            ->where('game_versions.game_id', $game->id)
            ->select([
                'users.username',
                DB::raw('MAX(scores.score) as score'),
                DB::raw('(SELECT created_at FROM scores s2 
                          WHERE s2.user_id = scores.user_id 
                          AND s2.game_version_id IN (' . implode(',', $gameVersionIds) . ') 
                          AND s2.score = MAX(scores.score) 
                          LIMIT 1) as timestamp')
            ])
            ->groupBy('users.id', 'users.username')
            ->orderByDesc('score')
            ->get()
            ->map(function ($item) {
                return [
                    'username' => $item->username,
                    'score' => (int) $item->score,
                    'timestamp' => $item->timestamp
                ];
            });

        return response()->json([
            'scores' => $scores
        ], 200);
}

public function storeScore(Request $request, $slug)
{
        // Validasi input
        $request->validate([
            'score' => 'required|numeric'
        ]);
        
        // Cari game berdasarkan slug
        $game = Game::where('slug', $slug)->first();
        
        // Jika game tidak ditemukan
        if (!$game) {
            return response()->json([
                'status' => 'error',
                'message' => 'Game not found'
            ], 404);
        }
        
        // Ambil versi game terbaru
        $latestVersion = GameVersion::where('game_id', $game->id)
                            ->orderBy('created_at', 'desc')
                            ->first();
        
        // Jika tidak ada versi game
        if (!$latestVersion) {
            return response()->json([
                'status' => 'error',
                'message' => 'Versin game not found'
            ], 404);
        }
        
        // Buat skor baru
        $score = new Score();
        $score->user_id = Auth::id(); // ID pengguna yang login
        $score->game_version_id = $latestVersion->id;
        $score->score = $request->score;
        $score->save();
        
        // Berikan respons sukses
        return response()->json([
            'status' => 'success',
        ], 201); // 201 Created
}

}
