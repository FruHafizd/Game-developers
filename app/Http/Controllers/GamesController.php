<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Score;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
}
