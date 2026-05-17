<?php


use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$email = getenv('ADMIN_EMAIL') ?: 'admin@elyo.local';
$password = getenv('ADMIN_PASSWORD') ?: 'Admin123!ChangeMe';

$data = [
    'email' => $email,
    'password' => Hash::make($password),
];

if (Schema::hasColumn('users', 'name')) {
    $data['name'] = getenv('ADMIN_NAME') ?: 'ELYO Admin';
}

if (Schema::hasColumn('users', 'first_name')) {
    $data['first_name'] = getenv('ADMIN_FIRST_NAME') ?: 'ELYO';
}

if (Schema::hasColumn('users', 'last_name')) {
    $data['last_name'] = getenv('ADMIN_LAST_NAME') ?: 'Admin';
}

if (Schema::hasColumn('users', 'role')) {
    $data['role'] = getenv('ADMIN_ROLE') ?: 'ELYO_ADMIN';
}

if (Schema::hasColumn('users', 'status')) {
    $data['status'] = getenv('ADMIN_STATUS') ?: 'active';
}

if (Schema::hasColumn('users', 'email_verified_at')) {
    $data['email_verified_at'] = now();
}

if (Schema::hasColumn('users', 'remember_token')) {
    $data['remember_token'] = Str::random(10);
}

$user = User::updateOrCreate(
    ['email' => $email],
    $data
);

echo "Admin user created or updated:\n";
echo "ID: {$user->id}\n";
echo "Email: {$user->email}\n";

if (isset($user->role)) {
    echo "Role: {$user->role}\n";
}

echo "\nPassword: {$password}\n";
echo "Change this password after first login.\n";
