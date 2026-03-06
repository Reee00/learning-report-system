<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class School extends Model
{
    protected $fillable = ['name', 'address', 'pic_name'];

    public function classes()
    {
        return $this->hasMany(SchoolClass::class, 'school_id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}