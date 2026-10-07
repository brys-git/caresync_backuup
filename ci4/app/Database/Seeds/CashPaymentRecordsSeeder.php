<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * cash_payment_records was retired (see migration
 * 2026-09-15-040000_RetireCashPaymentRecords.php, renamed to
 * cash_payment_records_archive) in favour of a single ledger: the payments
 * table, with payment_method = 'cash'. This seeder used to CREATE TABLE IF
 * NOT EXISTS the retired table directly with raw SQL, bypassing the
 * migration system entirely - running it would have silently resurrected a
 * table the app no longer reads or writes. Left as a documented no-op
 * instead of deleting the file, so `php spark db:seed CashPaymentRecords`
 * (if anything still calls it) fails loudly by doing nothing rather than
 * quietly recreating dead schema.
 */
class CashPaymentRecordsSeeder extends Seeder
{
    public function run()
    {
        // Intentionally does nothing. See class docblock.
    }
}
