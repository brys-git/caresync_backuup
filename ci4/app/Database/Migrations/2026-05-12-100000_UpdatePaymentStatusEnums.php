<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdatePaymentStatusEnums extends Migration
{
    public function up()
    {
        // Update payment statuses from (pending/paid/cancelled) to (awaiting_verification/verified/rejected)
        // This migration handles the transition for the payment workflow correction phase
        
        $db = $this->db;
        
        // First, update all existing records to use new terminology
        // pending -> awaiting_verification
        // paid -> verified
        // cancelled -> rejected
        
        $db->query("UPDATE payments SET status = 'awaiting_verification' WHERE status = 'pending'");
        $db->query("UPDATE payments SET status = 'verified' WHERE status = 'paid'");
        $db->query("UPDATE payments SET status = 'rejected' WHERE status = 'cancelled'");
        
        // If the payments table has an ENUM type, we need to alter the column
        // This is database-specific and depends on the exact schema
    }

    public function down()
    {
        // Rollback: revert to old statuses
        $db = $this->db;
        
        $db->query("UPDATE payments SET status = 'pending' WHERE status = 'awaiting_verification'");
        $db->query("UPDATE payments SET status = 'paid' WHERE status = 'verified'");
        $db->query("UPDATE payments SET status = 'cancelled' WHERE status = 'rejected'");
    }
}
