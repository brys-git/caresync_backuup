<?php

namespace App\Services;

use Config\CareSync;

/**
 * Overdue wording helpers.
 *
 * The overdue RULE no longer lives here. The old rule (after 60 days,
 * months_paid is reset to 0) erased a member's history; it was replaced by
 * one rule in PlanLedgerService, driven by Config\CareSync:
 *
 *   grace period -> Lapsed (on hold, nothing erased) -> optionally Forfeited
 *
 * What is left here is only the text used by notification rows that were
 * created before the change (NotificationService re-derives their wording
 * from the plan's due date).
 */
class OverduePolicyService
{
    public function graceDays(): int
    {
        return (int) config(CareSync::class)->gracePeriodDays;
    }

    /** Days left of the grace period, given days overdue (0 once it has passed). */
    public function daysUntilForfeiture(int $daysOverdue): int
    {
        return max(0, $this->graceDays() - $daysOverdue);
    }

    /**
     * @return array{days_overdue: int, days_left: int, message: string}
     */
    public function describeOverdue(string $dueDate, ?string $today = null): array
    {
        $today = $today ?? date('Y-m-d');
        $daysOverdue = max(0, (int) floor((strtotime($today) - strtotime($dueDate)) / 86400));
        $daysLeft = $this->daysUntilForfeiture($daysOverdue);
        $missedOn = date('F j, Y', strtotime($dueDate));
        $grace = $this->graceDays();

        $message = $daysOverdue === 0
            ? "Your monthly contribution due on {$missedOn} is no longer overdue."
            : ($daysLeft > 0
                ? "Your monthly contribution due on {$missedOn} is now {$daysOverdue} day(s) overdue. You have {$daysLeft} day(s) left to pay it before your plan is put on hold (a {$grace}-day grace period applies from that due date)."
                : "Your monthly contribution due on {$missedOn} is {$daysOverdue} day(s) overdue and the {$grace}-day grace period has passed, so your plan is on hold. Nothing has been erased - pay what is overdue at your branch to reinstate it.");

        return [
            'days_overdue' => $daysOverdue,
            'days_left'    => $daysLeft,
            'message'      => $message,
        ];
    }
}
