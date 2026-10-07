<?php

namespace App\Commands;

use App\Services\MembershipService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Client dashboard rebuild, Phase 0 (months_paid bug fix): a one-off
 * backfill, not a scheduled job. months_paid used to be written directly at
 * a handful of call sites - one of which overwrote rather than accumulated
 * - so any plan with more than one payment recorded before this fix may
 * already be wrong. This recalculates every plan's months_paid from its
 * actual verified payment history (MembershipService::recalculateMonthsPaid(),
 * the same method every live call site now uses going forward), scoped to
 * each plan's contribution_cycle_started_at so a plan that's already been
 * through a forfeiture/claim reset isn't restored past that reset.
 *
 * Safe to run more than once - recalculation is idempotent.
 */
class RecalculateMonthsPaid extends BaseCommand
{
    protected $group       = 'Membership';
    protected $name        = 'membership:recalculate-months-paid';
    protected $description = 'One-off backfill: recompute months_paid for every plan from its verified payment history.';
    protected $usage       = 'php spark membership:recalculate-months-paid';
    protected $arguments   = [];
    protected $options     = [];

    public function run(array $params = [])
    {
        $db = db_connect();
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
