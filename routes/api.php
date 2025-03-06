<?php

uri('/api/v1/register', 'App\Controllers\AuthController', 'register', 'POST');
uri('/api/v1/login', 'App\Controllers\AuthController', 'login', 'POST');