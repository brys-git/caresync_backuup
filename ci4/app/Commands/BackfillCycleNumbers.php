<?php

namespace App\Commands;

use App\Services\MembershipService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Entitlement cycle model: one-off backfill for data that predates
 * plans.current_cycle_number / payments.cycle_number /
 * service_applications.cycle_number. Every existing plan already starts
 * at current_cycle_number = 1 (the migration's column default), and no
 * plan has ever gone through an approved entitlement claim yet (confirmed
 * against the live DB before this command was written) - so there is no
 * cycle history to actually re-derive. Every existing payment and
 * application simply belongs to cycle 1. This stamps that in, then
 * re-runs MembershipService::recalculateMonthsPaid() for every plan so
 * months_paid reflects the now-cycle-scoped SUM: recalculateMonthsPaid()
 * started filtering by cycle_number the same time this command was added,
 * and before this runs, every existing payment's cycle_number is NULL,
 * which that filter would treat as "not in the current cycle" and
 * undercount to 0.
 *
 * Safe to run more than once - every write here only targets rows that
 * still have a NULL cycle_number, and recalculation is itself idempotent.
 */
class BackfillCycleNumbers extends BaseCommand
{
    protected $group       = 'Membership';
    protected $name        = 'membership:backfill-cycle-numbers';
    protected $description = 'One-off backfill: stamp cycle_number = 1 on pre-existing payments/applications, then recompute months_paid for every plan.';
    protected $usage       = 'php spark membership:backfill-cycle-numbers';
    protected $arguments   = [];
    protected $options     = [];

    public function run(array $params = [])
    {
        $db = db_connect();

        $paymentsUpdated = $db->table('payments')
            ->where('cycle_number IS NULL', null, false)
            ->whereIn('status', ['paid', 'verified'])
            ->update(['cycle_number' => 1]);
        CLI::write('Stamped cycle_number = 1 on previously-unstamped verified payments.', 'yellow');

        $applicationsUpdated = $db->table('service_applications')
            ->where('cycle_number IS NULL', null, false)
            ->update(['cycle_number' => 1]);
        CLI::write('Stamped cycle_number = 1 on previously-unstamped service applications.', 'yellow');

        $plans = $db->table('plans')->select('plan_id, months_paid')->get()->getResultArray();
        CLI::write('Recalculating months_paid for ' . count($plans) . ' plan(s)...', 'yellow');

        $membershipService = new MembershipService();
        $changed = 0;

        foreach ($plans as $plan) {
            $planId = (int) $plan['plan_id'];
            $before = (int) ($plan['months_paid'] ?? 0);
            $after = $membershipService->recalculateMonthsPaid($planId);

            if ($after !== $before) {
                $changed++;
                CLI::write("  plan #{$planId}: months_paid {$before} -> {$after}", 'cyan');
            }
        }

        CLI::write('Done. ' . $changed . ' plan(s) corrected out of ' . count($plans) . ' checked.', 'green');
    }
}
