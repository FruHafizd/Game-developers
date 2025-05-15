<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Score extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'game_version_id',
        'score',
        'version',
    ];

    public function game()  {
        return $this->belongsTo(Game::class);
    }

    public function gameVersion()
    {
        return $this->belongsTo(GameVersion::class);
    }
    
}
