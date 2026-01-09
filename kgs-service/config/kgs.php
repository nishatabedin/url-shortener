<?php

return [
  'pool_key' => env('KGS_POOL_KEY', 'kgs:pool'),
  'pool_min' => (int) env('KGS_POOL_MIN', 5000),
  'pool_target' => (int) env('KGS_POOL_TARGET', 20000),
  'admin_token' => env('KGS_ADMIN_TOKEN'),
];
