<?php
// Temporary audit script (stabilization task). Safe: read-only.
$db = new PDO('sqlite:' . __DIR__ . '/database/database.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== users DDL ===\n";
echo $db->query("SELECT sql FROM sqlite_master WHERE name='users'")->fetchColumn(), "\n";

echo "=== users ===\n";
foreach ($db->query('SELECT id,name,email,role,school_id FROM users ORDER BY id') as $r) {
    echo "{$r['id']} | {$r['email']} | role={$r['role']} | school_id=" . var_export($r['school_id'], true) . "\n";
}

echo "=== tables ===\n";
foreach ($db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name") as $r) {
    echo $r['name'], "\n";
}

echo "=== row counts ===\n";
foreach (['schools','classes','students','coach_classes','reports','report_attendances','report_media','programs','program_classes','school_user'] as $t) {
    try {
        echo str_pad($t, 22) . $db->query("SELECT COUNT(*) FROM \"$t\"")->fetchColumn() . "\n";
    } catch (Throwable $e) {
        echo str_pad($t, 22) . 'MISSING' . "\n";
    }
}

echo "=== data integrity ===\n";
$checks = [
    'orphan students (no class)'            => 'SELECT COUNT(*) FROM students s LEFT JOIN classes c ON c.id=s.class_id WHERE c.id IS NULL',
    'orphan classes (no school)'            => 'SELECT COUNT(*) FROM classes c LEFT JOIN schools s ON s.id=c.school_id WHERE s.id IS NULL',
    'orphan coach_classes (no coach)'       => 'SELECT COUNT(*) FROM coach_classes cc LEFT JOIN users u ON u.id=cc.coach_id WHERE u.id IS NULL',
    'coach_classes with non-coach user'     => "SELECT COUNT(*) FROM coach_classes cc JOIN users u ON u.id=cc.coach_id WHERE u.role<>'coach'",
    'orphan coach_classes (no class)'       => 'SELECT COUNT(*) FROM coach_classes cc LEFT JOIN classes c ON c.id=cc.class_id WHERE c.id IS NULL',
    'attendance without student'            => 'SELECT COUNT(*) FROM report_attendances ra LEFT JOIN students s ON s.id=ra.student_id WHERE s.id IS NULL',
    'attendance without report'             => 'SELECT COUNT(*) FROM report_attendances ra LEFT JOIN reports r ON r.id=ra.report_id WHERE r.id IS NULL',
    'report school<>class school (mismatch)' => 'SELECT COUNT(*) FROM reports r JOIN classes c ON c.id=r.class_id WHERE c.school_id <> r.school_id',
    'attendance student not in report class' => 'SELECT COUNT(*) FROM report_attendances ra JOIN reports r ON r.id=ra.report_id JOIN students s ON s.id=ra.student_id WHERE s.class_id <> r.class_id',
    'reports by non-coach user'             => "SELECT COUNT(*) FROM reports r JOIN users u ON u.id=r.coach_id WHERE u.role<>'coach'",
    'school_user pointing to missing school'=> 'SELECT COUNT(*) FROM school_user su LEFT JOIN schools s ON s.id=su.school_id WHERE s.id IS NULL',
    'school_pic without any school scope'   => "SELECT COUNT(*) FROM users u WHERE u.role='school_pic' AND u.school_id IS NULL AND NOT EXISTS (SELECT 1 FROM school_user su WHERE su.user_id=u.id)",
    'finance without any school scope'      => "SELECT COUNT(*) FROM users u WHERE u.role='finance' AND u.school_id IS NULL AND NOT EXISTS (SELECT 1 FROM school_user su WHERE su.user_id=u.id)",
    'duplicate coach assignments'           => 'SELECT COUNT(*) FROM (SELECT coach_id,class_id FROM coach_classes GROUP BY coach_id,class_id HAVING COUNT(*)>1)',
    'program_classes orphan class'          => 'SELECT COUNT(*) FROM program_classes pc LEFT JOIN classes c ON c.id=pc.class_id WHERE c.id IS NULL',
];
foreach ($checks as $label => $sql) {
    try {
        echo str_pad($label, 42) . $db->query($sql)->fetchColumn() . "\n";
    } catch (Throwable $e) {
        echo str_pad($label, 42) . 'ERR: ' . $e->getMessage() . "\n";
    }
}
