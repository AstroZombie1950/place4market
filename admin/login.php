<?php
/* ==========================================================================
	Admin: old login address. The admin has no login form of its own: staff
	sign in on the site (auth.php). bootstrap.php sends everyone else to the
	home page, staff go on to the dashboard.
	========================================================================== */
require __DIR__ . '/../source/inc/admin/bootstrap.php';

redirect('index.php');
