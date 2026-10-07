<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * My profile redesign: a member's photo (stored under WRITEPATH, served by
 * the profile photo route) and when the password last changed, shown on the
 * Sign-in & security row.
 */
class AddProfilePhotoAndPasswordChangedAt extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('profile_photo', 'users')) {
            $this->forge->addColumn('users', [
                'profile_photo' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'contact_number'],
            ]);
        }

        if (! $this->db->fieldExists('password_changed_at', 'users')) {
            $this->forge->addColumn('users', [
                'password_changed_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'password_hash'],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('profile_photo', 'users')) {
            $this->forge->dropColumn('users', 'profile_photo');
        }

        if ($this->db->fieldExists('password_changed_at', 'users')) {
            $this->forge->dropColumn('users', 'password_changed_at');
        }
    }
}
