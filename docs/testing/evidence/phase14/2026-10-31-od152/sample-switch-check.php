<?php
// OD-152: with the owner's server's own environment, does submission refuse a sample page on smart_go? Validation only,
// in a READ ONLY database session; nothing is saved.
use App\Models\Page;
use App\Services\Content\PagePayload;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
require '/home/administrator/Documents/website/smartGrow/vendor/autoload.php';
$app = require '/home/administrator/Documents/website/smartGrow/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.connections.mysql.options' => [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION TRANSACTION READ ONLY']]);
DB::purge('mysql');
$db = DB::selectOne('SELECT DATABASE() AS db, @@session.transaction_read_only AS ro');
$try = function (string $template, bool $forSubmission): string {
    try {
        app(PagePayload::class)->validate(new Page, ['title' => 'Check', 'slug' => 'od152-check', 'template' => $template, 'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Check.</p>']]]], $forSubmission);
        return 'accepted';
    } catch (ValidationException $exception) {
        return 'refused: '.implode(' ', $exception->errors()['template'] ?? array_merge(...array_values($exception->errors())));
    }
};
echo json_encode(['database' => $db->db, 'read_only' => (int) $db->ro, 'switch' => config('backoffice.sample_content'),
    'sample page, saved as a draft' => $try('sample', false), 'sample page, submitted' => $try('sample', true), 'standard page, submitted' => $try('standard', true)], JSON_PRETTY_PRINT), PHP_EOL;
