<?php
/**
 * Role layout for "admin".
 *
 * Kept as a file name so existing controllers can go on passing
 * 'role_layout' => 'layouts/admin' without any change. All markup lives
 * in layouts/_shell.php.
 */
$this->setData(['cs_role' => 'admin'], 'raw');
?>
<?= $this->include('layouts/_shell') ?>
