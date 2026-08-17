<?php
$db = new PDO('sqlite:' . __DIR__ . '/database/database.sqlite');
echo $db->query("SELECT sql FROM sqlite_master WHERE name='users'")->fetchColumn(), PHP_EOL;
echo '--- USERS ---', PHP_EOL;
foreach ($db->query('SELECT id,name,email,role,school_id,password FROM users') as $r) {
    echo $r['id'] . ' | ' . $r['email'] . ' | role=' . $r['role'] . ' | pwlen=' . strlen($r['password']) . ' | pwprefix=' . substr($r['password'], 0, 7) . PHP_EOL;
}
echo '--- TABLES ---', PHP_EOL;
foreach ($db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name") as $r) {
    echo $r['name'], PHP_EOL;
}
