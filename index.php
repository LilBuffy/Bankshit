<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('/dashboard.php');
} else {
    redirect('/landing.php');
}


/*

FUCCCCKCKCKCKKK I NEED TO REMEMBER THESE CREDENTIALS BTW I HATE NIG-

| Admin | `admin` | `Password123!` | Full admin console at `/admin/` |
| Customer | `juan.delacruz` | `Password123!` | Demo balance ₦V 25,000 |
| Customer | `maria.santos` | `Password123!` | Demo balance ₦V 8,500.50, has an active demo loan |


*/
