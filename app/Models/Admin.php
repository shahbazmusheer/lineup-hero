<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable; // Use Authenticatable
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Admin extends Authenticatable implements JWTSubject // Implement JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role_id'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // JWTSubject Methods
    public function getJWTIdentifier() { return $this->getKey(); }
    public function getJWTCustomClaims() {
         return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'type' => 'admin', // Identify type in token
        ];
    }

    // Define Relationships (Admins might manage Orgs, Users, etc.)
    // public function managedOrganizations() { ... }
}
