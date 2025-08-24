<?php
// index.php – Локальный видео-конвертер
// ================= PHP BACKEND =================
$message = $downloadLink = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['video'])) {
    $allowed = ['video/mp4', 'video/x-msvideo', 'video/quicktime', 'video/x-matroska', 'video/webm'];
    if (!in_array($_FILES['video']['type'], $allowed)) {
        $message = 'Недопустимый формат файла.';
    } elseif ($_FILES['video']['error'] !== UPLOAD_ERR_OK) {
        $message = 'Ошибка загрузки файла.';
    } else {
        $uploadsDir = __DIR__ . '/uploads';
        if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0775, true);

        $origName = basename($_FILES['video']['name']);
        $tmpPath  = $_FILES['video']['tmp_name'];
        $input    = $uploadsDir . '/' . uniqid('in_') . '_' . $origName;

        if (!move_uploaded_file($tmpPath, $input)) {
            $message = 'Не удалось сохранить файл.';
        } else {
            // параметры из формы
            $format   = $_POST['format']   ?? 'mp4';
            $quality  = (int)($_POST['quality'] ?? 80); // 10-100
            $resolution = $_POST['resolution'] ?? 'original';
            $fps      = $_POST['fps'] ?? 'original';
            $audioBit = $_POST['audio'] ?? '128';
            $codec    = $_POST['codec'] ?? 'h264';

            // Построение команды ffmpeg
            $output = $uploadsDir . '/' . uniqid('out_') . '.' . $format;
            $vcodec = $codec === 'h264' ? 'libx264' : ($codec === 'h265' ? 'libx265' : 'libvpx-vp9');
            $crf    = 51 - round($quality * 0.41); // приближенная шкала
            $vf     = $resolution !== 'original' ? "-vf scale={$resolution}" : '';
            $r      = $fps !== 'original' ? "-r {$fps}" : '';

            $cmd = sprintf('ffmpeg -i %s -c:v %s -crf %d %s %s -b:a %sk -y %s 2>&1',
                escapeshellarg($input), $vcodec, $crf, $vf, $r, (int)$audioBit, escapeshellarg($output));
            exec($cmd, $outLog, $exitCode);
            if ($exitCode !== 0) {
                $message = 'Произошла ошибка конвертации.';
            } else {
                $downloadLink = 'uploads/' . basename($output);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Локальный видео конвертер</title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.1/dist/tailwind.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
</head>
<body class="min-h-screen bg-slate-900 flex items-center justify-center p-4">
<div class="w-full max-w-5xl bg-slate-800/70 rounded-2xl backdrop-blur p-6">
  <h1 class="text-2xl text-center font-bold text-white mb-6 flex items-center justify-center gap-3"><i class="fas fa-video text-blue-400"></i> Локальный видео конвертер (PHP)</h1>

  <?php if($message): ?>
    <div class="bg-red-500/20 text-red-300 p-4 rounded mb-4"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <?php if($downloadLink): ?>
    <div class="bg-green-500/20 text-green-300 p-4 rounded mb-4 flex items-center justify-between">
      <span>Конвертация успешна!</span>
      <a class="underline" href="<?= htmlspecialchars($downloadLink) ?>" download>Скачать файл</a>
    </div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" class="space-y-6">
    <div class="flex flex-col md:flex-row gap-6">
      <!-- File input -->
      <div class="flex-1">
        <label class="block text-slate-300 mb-2">Выберите видео</label>
        <input type="file" name="video" accept="video/*" required class="file:border-none file:bg-blue-600 file:text-white file:px-4 file:py-2 file:rounded-lg bg-slate-700/50 text-slate-200 rounded-lg w-full" />
      </div>
      <!-- Format -->
      <div>
        <label class="block text-slate-300 mb-2">Формат</label>
        <select name="format" class="bg-slate-700/50 text-slate-200 rounded-lg p-2">
          <option value="mp4">MP4</option>
          <option value="webm">WEBM</option>
          <option value="avi">AVI</option>
          <option value="mov">MOV</option>
          <option value="mkv">MKV</option>
        </select>
      </div>
      <!-- Quality -->
      <div>
        <label class="block text-slate-300 mb-2">Качество (%)</label>
        <input type="number" name="quality" min="10" max="100" value="80" class="bg-slate-700/50 text-slate-200 rounded-lg p-2 w-24" />
      </div>
    </div>

    <div class="flex flex-col md:flex-row gap-6">
      <!-- Resolution -->
      <div>
        <label class="block text-slate-300 mb-2">Разрешение</label>
        <select name="resolution" class="bg-slate-700/50 text-slate-200 rounded-lg p-2">
          <option value="original">Оригинал</option>
          <option value="3840:2160">4K</option>
          <option value="1920:1080">1080p</option>
          <option value="1280:720">720p</option>
          <option value="854:480">480p</option>
          <option value="640:360">360p</option>
        </select>
      </div>
      <!-- FPS -->
      <div>
        <label class="block text-slate-300 mb-2">FPS</label>
        <select name="fps" class="bg-slate-700/50 text-slate-200 rounded-lg p-2">
          <option value="original">Оригинал</option>
          <option value="60">60</option>
          <option value="50">50</option>
          <option value="30">30</option>
          <option value="25">25</option>
          <option value="24">24</option>
        </select>
      </div>
      <!-- Audio bitrate -->
      <div>
        <label class="block text-slate-300 mb-2">Аудио (kbps)</label>
        <select name="audio" class="bg-slate-700/50 text-slate-200 rounded-lg p-2">
          <option value="128">128</option>
          <option value="192">192</option>
          <option value="256">256</option>
          <option value="320">320</option>
        </select>
      </div>
      <!-- Codec -->
      <div>
        <label class="block text-slate-300 mb-2">Кодек</label>
        <select name="codec" class="bg-slate-700/50 text-slate-200 rounded-lg p-2">
          <option value="h264">H.264</option>
          <option value="h265">H.265</option>
          <option value="vp9">VP9</option>
        </select>
      </div>
    </div>

    <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg w-full md:w-auto"><i class="fas fa-cogs mr-2"></i>Конвертировать</button>
  </form>
</div>
</body>
</html>