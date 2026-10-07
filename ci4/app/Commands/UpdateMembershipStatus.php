<?php

namespace App\Commands;

use App\Services\PlanLedgerService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class UpdateMembershipStatus extends BaseCommand
{
    protected $group       = 'Membership';
    protected $name        = 'membership:update-status';
    protected $description = 'Daily check: refresh every plan (active / grace period / lapsed / forfeited) and send the overdue reminders. Never erases payment history.';
    protected $usage       = 'php spark membership:update-status';
    protected $arguments   = [];
    protected $options     = [];

    public function run(array $params = [])
    {
        CLI::write('Running the daily plan check...', 'yellow');

        try {
            $result = (new PlanLedgerService())->runDailyCheck();
        } catch (\Throwable $e) {
            CLI::error('Error: ' . $e->getMessage());

            return 1;
        }

        CLI::write('Daily plan check completed:', 'green');
        CLI::write('  - Plans checked: ' . $result['checked'], 'white');
        CLI::write('  - In grace period: ' . $result['grace'], 'yellow');
        CLI::write('  - Lapsed (on hold): ' . $result['lapsed'], 'red');
        CLI::write('  - Forfeited: ' . $result['forfeited'], 'red');
        CLI::write('  - Reminders sent: ' . $result['notified'], 'cyan');

        return 0;
    }
}
