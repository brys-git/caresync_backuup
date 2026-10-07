<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * CareSync business rules for contributions that are overdue.
 *
 * ONE place for the numbers. Every screen and the daily check read them
 * through PlanLedgerService, so changing a value here changes the client
 * details page, the client's own dashboard, Existing member records, the
 * reports, the reminders and the daily check together.
 *
 * Every value below still needs to be confirmed against the Kaagapay
 * contract.
 *
 * Can also be overridden from .env, e.g.  caresync.gracePeriodDays = 45
 */
class CareSync extends BaseConfig
{
    /**
     * Days a member has, counted from the due date of the OLDEST unpaid
     * contribution, to pay everything overdue before the plan lapses.
     * Confirm with Kaagapay contract.
     */
    public int $gracePeriodDays = 60;

    /**
     * Where the grace period starts counting.
     * 'first_missed_due_date' = the due date of the oldest unpaid contribution
     * (the only mode implemented). Confirm with Kaagapay contract.
     */
    public string $graceStartsFrom = 'first_missed_due_date';

    /**
     * Peso amount charged ON TOP of the overdue contributions to reinstate a
     * Lapsed plan. 0 until confirmed. Confirm with Kaagapay contract.
     */
    public float $reinstatementFee = 0;

    /**
     * How many days a plan may stay Lapsed before it becomes Forfeited.
     * null = never forfeit automatically (a Lapsed plan just stays on hold
     * and keeps all its history). Confirm with Kaagapay contract.
     */
    public ?int $forfeitAfterLapseDays = null;

    /**
     * Whether a Lapsed (on hold) plan may still request services / claim its
     * package. A Forfeited plan never can. Confirm with Kaagapay contract.
     */
    public bool $lapsedCanClaimServices = false;
}
