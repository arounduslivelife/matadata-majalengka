<?php

$pageTitle = 'Bot Telegram';

$activeMenu = 'telegram';

$pageSubtitle = 'Integrasi bot Telegram dengan data MySQL dan Groq AI';

require_once __DIR__ . '/_init.php';

require_once dirname(__DIR__) . '/includes/TelegramBotService.php';

require_once dirname(__DIR__) . '/includes/MatadataContextBuilder.php';



$tg = AppConfig::telegram();

$flash = '';

$flashType = 'success';

$testResult = null;

$webhookInfo = null;



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? 'save';



    if ($action === 'save') {

        $idsRaw = trim($_POST['allowed_chat_ids'] ?? '');

        $ids = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $idsRaw))));



        $saved = AppConfig::saveTelegram([

            'enabled' => !empty($_POST['enabled']),

            'bot_token' => trim($_POST['bot_token'] ?? ''),

            'webhook_secret' => trim($_POST['webhook_secret'] ?? ''),

            'allowed_chat_ids' => $ids,

            'model' => trim($_POST['model'] ?? 'llama-3.3-70b-versatile'),

            'max_tokens' => max(100, min(800, (int) ($_POST['max_tokens'] ?? 400))),

            'welcome_message' => trim($_POST['welcome_message'] ?? ''),

            'system_extra' => trim($_POST['system_extra'] ?? ''),

        ]);

        $tg = AppConfig::telegram();

        $flash = $saved ? 'Pengaturan disimpan.' : 'Gagal menyimpan config.json (periksa izin file).';

        $flashType = $saved ? 'success' : 'danger';

    }



    if ($action === 'set_webhook') {

        try {

            $bot = new TelegramBotService($pdo);

            $url = webhookPublicUrl();

            $res = $bot->setWebhook($url);

            $flash = !empty($res['ok']) ? 'Webhook terpasang: ' . $url : ('Gagal: ' . ($res['description'] ?? 'unknown'));

            $flashType = !empty($res['ok']) ? 'success' : 'danger';

        } catch (Throwable $e) {

            $flash = $e->getMessage();

            $flashType = 'danger';

        }

    }



    if ($action === 'test_question') {

        $q = trim($_POST['test_question'] ?? '');

        if ($q === '') {

            $flash = 'Isi pertanyaan uji coba.';

            $flashType = 'warning';

        } else {

            try {

                $bot = new TelegramBotService($pdo);

                $testResult = $bot->answerQuestion($q);

                $flash = 'Jawaban uji coba berhasil dibuat.';

            } catch (Throwable $e) {

                $flash = 'Uji coba gagal: ' . $e->getMessage();

                $flashType = 'danger';

            }

        }

    }

}



if (!empty($tg['bot_token'])) {

    try {

        $bot = new TelegramBotService($pdo);

        $webhookInfo = $bot->getWebhookInfo();

    } catch (Throwable $e) {

        $webhookInfo = ['ok' => false, 'description' => $e->getMessage()];

    }

}



$previewContext = '';

try {

    $builder = new MatadataContextBuilder($pdo);

    $previewContext = mb_substr($builder->build(), 0, 1200) . '...';

} catch (Throwable $e) {

    $previewContext = 'Error: ' . $e->getMessage();

}



$webhookUrl = webhookPublicUrl();



require_once __DIR__ . '/includes/layout.php';

adminRenderHeader($pageTitle, $activeMenu);

adminRenderAlert($flash, $flashType);

?>



<div class="row">

    <div class="col-lg-7 col-12">

        <div class="card card-outline card-primary">

            <div class="card-header">

                <h3 class="card-title"><i class="fas fa-cog"></i> Pengaturan Bot</h3>

                <div class="card-tools">

                    <span class="badge badge-<?= !empty($tg['enabled']) ? 'success' : 'secondary' ?>">

                        <?= !empty($tg['enabled']) ? 'Aktif' : 'Nonaktif' ?>

                    </span>

                </div>

            </div>

            <form method="post">

                <input type="hidden" name="action" value="save">

                <div class="card-body">

                    <div class="form-group mb-3">

                        <div class="custom-control custom-switch">

                            <input type="checkbox" class="custom-control-input" id="enabled" name="enabled" value="1" <?= !empty($tg['enabled']) ? 'checked' : '' ?>>

                            <label class="custom-control-label" for="enabled">Bot aktif</label>

                        </div>

                    </div>

                    <div class="form-group">

                        <label for="bot_token">Bot Token (@BotFather)</label>

                        <input type="password" class="form-control" id="bot_token" name="bot_token" value="<?= htmlspecialchars($tg['bot_token'] ?? '') ?>" autocomplete="off" placeholder="123456:ABC...">

                    </div>

                    <div class="form-group">

                        <label for="webhook_secret">Webhook secret (opsional)</label>

                        <input type="text" class="form-control" id="webhook_secret" name="webhook_secret" value="<?= htmlspecialchars($tg['webhook_secret'] ?? '') ?>" placeholder="string rahasia untuk URL webhook">

                        <small class="form-text text-muted">Disarankan agar webhook tidak bisa dipanggil sembarangan.</small>

                    </div>

                    <div class="form-group">

                        <label for="allowed_chat_ids">Chat ID diizinkan (kosong = semua)</label>

                        <textarea class="form-control" id="allowed_chat_ids" name="allowed_chat_ids" rows="2" placeholder="123456789, -1001234567890"><?= htmlspecialchars(implode(', ', $tg['allowed_chat_ids'] ?? [])) ?></textarea>

                        <small class="form-text text-muted">Dapatkan dari @userinfobot atau log webhook pertama.</small>

                    </div>

                    <div class="row">

                        <div class="col-md-6 form-group">

                            <label for="model">Model Groq</label>

                            <input type="text" class="form-control" id="model" name="model" value="<?= htmlspecialchars($tg['model'] ?? 'llama-3.3-70b-versatile') ?>">

                        </div>

                        <div class="col-md-6 form-group">

                            <label for="max_tokens">Max tokens jawaban</label>

                            <input type="number" class="form-control" id="max_tokens" name="max_tokens" min="100" max="800" value="<?= (int) ($tg['max_tokens'] ?? 400) ?>">

                        </div>

                    </div>

                    <div class="form-group">

                        <label for="welcome_message">Pesan /start</label>

                        <textarea class="form-control" id="welcome_message" name="welcome_message" rows="2"><?= htmlspecialchars($tg['welcome_message'] ?? '') ?></textarea>

                    </div>

                    <div class="form-group mb-0">

                        <label for="system_extra">Instruksi tambahan untuk AI (opsional)</label>

                        <textarea class="form-control" id="system_extra" name="system_extra" rows="2" placeholder="Contoh: Selalu sebut tahun data jika relevan."><?= htmlspecialchars($tg['system_extra'] ?? '') ?></textarea>

                    </div>

                </div>

                <div class="card-footer">

                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan</button>

                </div>

            </form>

        </div>



        <div class="card card-outline card-secondary">

            <div class="card-header">

                <h3 class="card-title"><i class="fas fa-vial"></i> Uji pertanyaan (tanpa Telegram)</h3>

            </div>

            <form method="post">

                <input type="hidden" name="action" value="test_question">

                <div class="card-body">

                    <div class="form-group mb-<?= $testResult ? '2' : '0' ?>">

                        <input type="text" class="form-control" name="test_question" placeholder="Kecamatan mana dana desa tertinggi?" required>

                    </div>

                    <?php if ($testResult): ?>

                        <div class="callout callout-info mb-0">

                            <p class="mb-0"><?= nl2br(htmlspecialchars($testResult)) ?></p>

                        </div>

                    <?php endif; ?>

                </div>

                <div class="card-footer">

                    <button type="submit" class="btn btn-secondary"><i class="fas fa-play mr-1"></i> Tes Groq</button>

                </div>

            </form>

        </div>

    </div>



    <div class="col-lg-5 col-12">

        <div class="card card-outline card-success">

            <div class="card-header">

                <h3 class="card-title"><i class="fas fa-link"></i> Webhook</h3>

            </div>

            <div class="card-body">

                <p class="text-muted small">URL untuk Telegram (HTTPS wajib di production):</p>

                <pre class="admin-pre-block admin-pre-scroll p-2 small mb-3"><?= htmlspecialchars($webhookUrl) ?></pre>

                <form method="post" class="mb-3">

                    <input type="hidden" name="action" value="set_webhook">

                    <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-plug mr-1"></i> Pasang webhook otomatis</button>

                </form>

                <?php if ($webhookInfo): ?>

                    <p class="small mb-0">

                        <strong>Status:</strong>

                        <span class="badge badge-<?= !empty($webhookInfo['ok']) ? 'success' : 'danger' ?>">

                            <?= !empty($webhookInfo['ok']) ? 'OK' : 'Error' ?>

                        </span>

                        <?php if (!empty($webhookInfo['result']['url'])): ?>

                            <br><span class="text-muted">URL terpasang:</span><br>

                            <code class="small"><?= htmlspecialchars($webhookInfo['result']['url']) ?></code>

                        <?php endif; ?>

                    </p>

                <?php endif; ?>

            </div>

            <div class="card-footer bg-white">

                <h6 class="mb-2"><i class="fas fa-list-ol text-muted mr-1"></i> Langkah singkat</h6>

                <ol class="small text-muted pl-3 mb-0">

                    <li>Buat bot via @BotFather, salin token.</li>

                    <li>Simpan &amp; aktifkan di form kiri.</li>

                    <li>Klik <em>Pasang webhook</em> (butuh HTTPS).</li>

                    <li>Chat bot di Telegram, kirim <code>/start</code>.</li>

                    <li>Lokal tanpa HTTPS: <code>php cli/telegram_poll.php</code></li>

                </ol>

            </div>

        </div>



        <div class="card card-outline card-secondary">

            <div class="card-header">

                <h3 class="card-title"><i class="fas fa-eye"></i> Pratinjau konteks data</h3>

            </div>

            <div class="card-body p-0">

                <pre class="admin-pre-block admin-pre-scroll p-3 small mb-0 border-0"><?= htmlspecialchars($previewContext) ?></pre>

            </div>

        </div>



        <div class="callout callout-warning mb-0">

            <p class="mb-0 small">

                <strong><i class="fas fa-key mr-1"></i> Groq API keys</strong> di <code>config.json</code> → <code>GROQ_API_KEYS</code>

                (<?= count(AppConfig::groqKeys()) ?> key). Bot memakai key yang sama dengan audit engine.

            </p>

        </div>

    </div>

</div>



<?php adminRenderFooter(); ?>

