<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'user_id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'username',
        'password_hash',
        'email',
        'first_name',
        'middle_name',
        'last_name',
        'name_extension',
        'contact_number',
        'role_id',
        'branch_id',
        'status',
        'last_login',
        'account_status',
        'is_plan_holder',
        'is_collector',
        'must_change_password',
        'temp_password_expires_at',
        'email_verification_token',
        'token_expiry',
        'reset_token',
        'reset_token_expiry',
        'profile_photo',
        'password_changed_at',
    ];
}
