<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php // The page is one shared partial: System Admin, Branch Admin and Staff all render exactly this. ?>
<?= view('partials/client_detail', get_defined_vars()) ?>
<?= $this->endSection() ?>
