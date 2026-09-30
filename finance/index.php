<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (current_user()) {
    redirect('dashboard.php');
}
require __DIR__ . '/landing.php';