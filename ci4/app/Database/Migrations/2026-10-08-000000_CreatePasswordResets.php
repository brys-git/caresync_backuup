<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Forgot-password flow. One row per request: the token is stored only as a
 * SHA-256 hash, expires, and is single-use (used_at). Requests for emails
 * that are not registered are recorded too (user_id and token_hash NULL) so
 * the 3-per-15-minutes limit applies equally and the response can never
 * reveal whether an email exists.
 *
 * Also adds users.temp_password_expires_at, used by the temporary-password
 * flow for accounts created by staff.
 */
class CreatePasswordResets extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'reset_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'email'      => ['type' => 'VARCHAR', 'constraint' => 100],
            'token_hash' => ['type' => 'CHAR', 'constraint' => 64, 'null' => true],
            'expires_at' => ['type' => 'DATETIME', 'null' => true],
            'used_at'    => ['type' => 'DATETIME', 'null' => true],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('reset_id', true);
        $this->forge->addKey('token_hash');
        $this->forge->addKey(['email', 'created_at']);
        $this->forge->createTable('password_resets', true);

        if (! $this->db->fieldExists('temp_password_expires_at', 'users')) {
            $this->forge->addColumn('users', [
                'temp_password_expires_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'must_change_password'],
            ]);
        }
    }

    public function down()
    {
        $this->forge->dropTable('password_resets', true);

        if ($this->db->fieldExists('temp_password_expires_at', 'users')) {
            $this->forge->dropColumn('users', 'temp_password_expires_at');
        }
    }
}
