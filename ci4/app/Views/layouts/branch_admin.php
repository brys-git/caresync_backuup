<?php
/**
 * Role layout for "branch_admin".
 *
 * Kept as a file name so existing controllers can go on passing
 * 'role_layout' => 'layouts/branch_admin' without any change. All markup lives
 * in layouts/_shell.php.
 */
$this->setData(['cs_role' => 'branch_admin'], 'raw');
?>
<?= $this->include('layouts/_shell') ?>
