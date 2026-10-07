<?php
/**
 * Role layout for "collector".
 *
 * Kept as a file name so existing controllers can go on passing
 * 'role_layout' => 'layouts/collector' without any change. All markup lives
 * in layouts/_shell.php.
 */
$this->setData(['cs_role' => 'collector'], 'raw');
?>
<?= $this->include('layouts/_shell') ?>
