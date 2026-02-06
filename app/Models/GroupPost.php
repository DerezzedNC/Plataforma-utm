<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'announcement_id',
        'title',
        'content',
        'posted_by',
    ];

    /**
     * Relación con el grupo
     */
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Relación con el aviso original (si existe)
     */
    public function announcement()
    {
        return $this->belongsTo(Announcement::class);
    }

    /**
     * Relación con el usuario que publicó el post
     */
    public function postedBy()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}




