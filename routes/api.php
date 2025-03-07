<?php


/* --------------------- AUTH ----------------------------- */
uri('/api/v1/register', 'App\Controllers\AuthController', 'register', 'POST');
uri('/api/v1/login', 'App\Controllers\AuthController', 'login', 'POST');
uri('/api/v1/logout', 'App\Controllers\AuthController', 'logout', 'GET');


/* --------------------- LINK ----------------------------- */
uri('/api/v1/links', 'App\Controllers\LinkController', 'getLinks', 'GET');
uri('/api/v1/links/{id}', 'App\Controllers\LinkController', 'getLink', 'GET');
uri('/api/v1/links', 'App\Controllers\LinkController', 'createLink', 'POST');
uri('/api/v1/links/{id}', 'App\Controllers\LinkController', 'updateLink', 'PUT');
uri('/api/v1/links/{id}', 'App\Controllers\LinkController', 'deleteLink', 'DELETE');

uri('/{shortcode}', 'App\Controllers\LinkController', 'redirect', 'GET');