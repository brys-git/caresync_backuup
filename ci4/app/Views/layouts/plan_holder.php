<?php
/**
 * Role layout for "plan_holder".
 *
 * Kept as a file name so existing controllers can go on passing
 * 'role_layout' => 'layouts/plan_holder' without any change. All markup lives
 * in layouts/_shell.php.
 */
$this->setData(['cs_role' => 'plan_holder'], 'raw');
?>
<?= $this->include('layouts/_shell') ?>
