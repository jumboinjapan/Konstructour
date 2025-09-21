<?php
// Copy to admin-token.php and set a strong secret (e.g., admin password).
// The value in this file will be compared with the X-Admin-Token header
// when saving server-side credentials via config-store.php.
return [
  'token' => 'REPLACE_WITH_ADMIN_PASSWORD'
];


