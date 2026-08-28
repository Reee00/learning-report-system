<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// 1) GET /login to grab a CSRF token + session cookie
$req = Illuminate\Http\Request::create('http://localhost:8000/login', 'GET');
$res = $kernel->handle($req);
echo "GET /login -> " . $res->getStatusCode() . PHP_EOL;
$token = csrf_token();

// 2) POST /login with the real seeded credentials
$req2 = Illuminate\Http\Request::create('http://localhost:8000/login', 'POST', [
    '_token'   => $token,
    'email'    => 'admin@lrs.com',
    'password' => 'password',
]);
$req2->setLaravelSession($req->session());
$res2 = $kernel->handle($req2);
echo "POST /login -> " . $res2->getStatusCode() . " Location: " . ($res2->headers->get('Location') ?? '(none)') . PHP_EOL;
echo "auth check after attempt: " . var_export(auth()->check(), true) . PHP_EOL;
echo "auth role: " . var_export(auth()->user()?->role, true) . PHP_EOL;
