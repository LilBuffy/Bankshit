<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('/dashboard.php');
} else {
    redirect('/login.php');
}
