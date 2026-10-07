<?php
/**
 * Role layout for "staff".
 *
 * Kept as a file name so existing controllers can go on passing
 * 'role_layout' => 'layouts/staff' without any change. All markup lives
 * in layouts/_shell.php.
 */
$this->setData(['cs_role' => 'staff'], 'raw');
?>
<?= $this->include('layouts/_shell') ?>
