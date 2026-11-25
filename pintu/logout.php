<?php
require_once __DIR__ . '/includes/bootstrap.php';
logout_user();
session_start();
set_flash('success', 'Anda berhasil logout.');
redirect(base_url('login.php'));