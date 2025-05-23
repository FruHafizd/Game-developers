<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'version',
        'storage_path',
        'deleted_at'
    ];

    public function game()  {
        return $this->belongsTo(Game::class);
    }
}
