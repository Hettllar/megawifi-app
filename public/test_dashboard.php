<?php
require __DIR__.'/../vendor/autoload.php';
\ = require_once __DIR__.'/../bootstrap/app.php';
\ = \->make(Illuminate\Contracts\Http\Kernel::class);
\->boot();

use App\Models\Router;
\ = Router::all();
echo 'Total routers in DB: ' . \->count() . PHP_EOL;
foreach (\ as \) {
    echo 'ID=' . \->id . ' Name=' . \->name . ' Status=' . \->status . PHP_EOL;
}
