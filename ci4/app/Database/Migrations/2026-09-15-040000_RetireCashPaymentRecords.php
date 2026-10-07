<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Retires cash_payment_records in favour of the payments table as the single
 * cash/GCash ledger (payments already has plan_id, months_covered,
 * reference_number, coverage_start/end, verified_at/verified_by - everything
 * cash_payment_records lacked). Investigated before writing this: nothing in
 * the app reads cash_payment_records except its own controller
 * (BranchAdmin\CashPaymentController, removed in this same change) and a
 * seeder that just recreates the table - no verification action, no
 * reconciliation, ever existed for it. client_name was free text with no
 * plan_id, so money recorded there could never advance plans.months_paid.
 *
 * This RENAMES rather than drops, on purpose: dropping is irreversible, and
 * while this dev database has zero rows in the table (checked directly), a
 * production copy might not. Renaming preserves every row as a queryable
 * audit trail/reconciliation source without the retired feature being
 * writable or confusable with the live payments ledger. Actually deleting
 * cash_payment_records_archive, once any real rows in it are reconciled into
 * payments (or confirmed empty), is a manual follow-up - not automated here.
 */
class RetireCashPaymentRecords extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cash_payment_records') && ! $this->db->tableExists('cash_payment_records_archive')) {
            $this->forge->renameTable('cash_payment_records', 'cash_payment_records_archive');
        }
    }

    public function down()
    {
        if ($this->db->tableExists('cash_payment_records_archive') && ! $this->db->tableExists('cash_payment_records')) {
            $this->forge->renameTable('cash_payment_records_archive', 'cash_payment_records');
        }
    }
}
