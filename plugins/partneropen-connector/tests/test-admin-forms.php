<?php

declare(strict_types=1);

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(string $value): string
    {
        return strip_tags($value);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $value): string
    {
        return trim(strip_tags($value));
    }
}
$test_options = [];

function get_option(string $key, mixed $default = false): mixed
{
    global $test_options;

    return array_key_exists($key, $test_options) ? $test_options[$key] : $default;
}

function update_option(string $key, mixed $value, mixed $autoload = true): bool
{
    global $test_options;
    $test_options[$key] = $value;

    return true;
}

function delete_option(string $key): bool
{
    global $test_options;
    unset($test_options[$key]);

    return true;
}

if (!function_exists('sanitize_email')) {
    function sanitize_email(string $value): string
    {
        return trim($value);
    }
}

require_once __DIR__ . '/compat.php';
require_once __DIR__ . '/../includes/Support/Validation.php';
require_once __DIR__ . '/../includes/Domain/Consent.php';
require_once __DIR__ . '/../includes/Admin/SetupPage.php';
require_once __DIR__ . '/../includes/Admin/StatusPage.php';

use PartnerOpen\Connector\Admin\SetupPage;
use PartnerOpen\Connector\Admin\StatusPage;
use PartnerOpen\Connector\Domain\Consent;

function assert_admin_form(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$required_scopes = [];
foreach (Consent::scope_meta() as $scope => $meta) {
    if (!empty($meta['required'])) {
        $required_scopes[] = $scope;
    }
}

$valid = SetupPage::sanitize_submission([
    'prefix' => 'partner',
    'partner_email' => 'owner@example.test',
    'scopes' => $required_scopes,
    'terms_ack' => '1',
]);
assert_admin_form($valid['valid'] === true, 'valid setup submission should pass');
assert_admin_form($valid['prefix'] === 'partner', 'valid prefix should be normalized');
assert_admin_form($valid['scopes'] === $required_scopes, 'required scopes should be retained in contract order');

$invalid_prefix = SetupPage::sanitize_submission([
    'prefix' => 'wp-admin',
    'partner_email' => 'owner@example.test',
    'scopes' => $required_scopes,
    'terms_ack' => '1',
]);
assert_admin_form($invalid_prefix['valid'] === false, 'reserved prefix should fail');
assert_admin_form($invalid_prefix['prefix'] === null, 'invalid prefix should normalize to null');

$missing_scope = SetupPage::sanitize_submission([
    'prefix' => 'partner',
    'partner_email' => 'owner@example.test',
    'scopes' => [],
    'terms_ack' => '1',
]);
assert_admin_form($missing_scope['valid'] === false, 'missing required consent should fail');
assert_admin_form($missing_scope['errors'] !== [], 'missing required consent should explain the error');

assert_admin_form(SetupPage::authorize_submission(true, true) === true, 'authorized setup request should pass');
assert_admin_form(SetupPage::authorize_submission(false, true) === false, 'missing capability should fail');
assert_admin_form(SetupPage::authorize_submission(true, false) === false, 'missing nonce should fail');
assert_admin_form(StatusPage::authorize_action(false, true) === false, 'status request without capability should fail');
assert_admin_form(StatusPage::authorize_action(true, false) === false, 'status request without nonce should fail');
assert_admin_form(SetupPage::TERMS_URL === 'https://partneropen.com/terms', 'setup should link to the canonical Terms page');
assert_admin_form(SetupPage::PRIVACY_URL === 'https://partneropen.com/privacy', 'setup should link to the canonical Privacy page');

function assert_nonce_before_post_action(string $path): void
{
    $source = file_get_contents($path);
    if (!is_string($source)) {
        throw new RuntimeException('unable to inspect admin source');
    }

    $nonce_position = strpos($source, '$_POST[self::NONCE_FIELD]');
    if ($nonce_position === false) {
        $nonce_position = strpos($source, '$raw[self::NONCE_FIELD]');
    }
    $action_position = strpos($source, '$raw[\'partneropen_connector_action\']');
    if ($action_position === false) {
        $action_position = strpos($source, '$_POST[\'partneropen_connector_action\']');
    }
    if ($nonce_position === false || $action_position === false || $nonce_position > $action_position) {
        throw new RuntimeException('admin handlers must verify the nonce before reading the action');
    }
}

assert_nonce_before_post_action(__DIR__ . '/../includes/Admin/SetupPage.php');
assert_nonce_before_post_action(__DIR__ . '/../includes/Admin/StatusPage.php');

$status_source = file_get_contents(__DIR__ . '/../includes/Admin/StatusPage.php');
if (!is_string($status_source) || strpos($status_source, 'SnapshotStore::all()') === false || strpos($status_source, 'SnapshotStore::published()') !== false) {
    throw new RuntimeException('snapshot deletion must include suspended Spaces through SnapshotStore::all()');
}

assert_admin_form(StatusPage::sanitize_disconnect(['confirm' => 'yes'])['valid'] === true, 'disconnect confirmation aliases should remain accepted');
assert_admin_form(StatusPage::sanitize_disconnect(['disconnect_confirm' => '0'])['valid'] === false, 'unchecked disconnect confirmation should fail');

$without_confirmation = StatusPage::sanitize_disconnect([]);
assert_admin_form($without_confirmation['valid'] === false, 'disconnect without confirmation should fail');

$disconnect = StatusPage::sanitize_disconnect(['disconnect_confirm' => '1']);
assert_admin_form($disconnect['valid'] === true, 'confirmed disconnect should pass');
assert_admin_form($disconnect['status'] === 'disconnected', 'disconnect result should mark status disconnected');
assert_admin_form($disconnect['preserve_snapshots'] === true, 'disconnect should preserve local snapshots');

require_once __DIR__ . '/../includes/Infrastructure/Options.php';
require_once __DIR__ . '/../includes/Application/SpaceRegistry.php';
require_once __DIR__ . '/../includes/Domain/Block.php';
require_once __DIR__ . '/../includes/Domain/Link.php';
require_once __DIR__ . '/../includes/Application/SnapshotStore.php';
$render_setup = new ReflectionMethod(SetupPage::class, 'render_setup_form');
$render_setup->setAccessible(true);
ob_start();
$render_setup->invoke(new SetupPage(), ['status' => 'local', 'prefix' => 'partner'], false);
$setup_html = (string) ob_get_clean();
foreach (Consent::scope_meta() as $scope => $meta) {
    assert_admin_form(
        strpos($setup_html, (string) $meta['recipient']) !== false,
        'setup should render the recipient for ' . $scope
    );
}

\PartnerOpen\Connector\Infrastructure\Options::save_spaces([
    'suspended-space' => [
        'id' => 'suspended-space',
        'slug' => 'suspended-space',
        'title' => 'Suspended Space',
        'status' => 'suspended',
        'snapshot_version' => 1,
        'published_at' => 1,
    ],
]);
update_option('partneropen_snapshot_suspended-space', ['space' => ['id' => 'suspended-space']], false);
$delete_method = new ReflectionMethod(StatusPage::class, 'handle_delete_snapshots');
$delete_method->setAccessible(true);
$delete_method->invoke(new StatusPage(), ['delete_snapshots_confirm' => '1']);
assert_admin_form(get_option('partneropen_snapshot_suspended-space', null) === null, 'snapshot deletion should remove suspended-space snapshots');

echo "PartnerOpen admin form tests: OK\n";
