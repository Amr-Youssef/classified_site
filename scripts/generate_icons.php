<?php
// Generates PNG icons and favicon.ico from logo-najd.jpg/png
// Usage: php scripts/generate_icons.php

declare(strict_types=1);

function fail(string $msg, int $code = 1): void {
    fwrite(STDERR, "[icons] ERROR: $msg\n");
    exit($code);
}

function logmsg(string $msg): void { fwrite(STDOUT, "[icons] $msg\n"); }

$root = dirname(__DIR__);
$logo = $root . DIRECTORY_SEPARATOR . 'logo-najd.jpg';
if (!is_file($logo)) {
    // Try png fallback
    $alt = $root . DIRECTORY_SEPARATOR . 'logo-najd.png';
    if (is_file($alt)) $logo = $alt; else fail('لم يتم العثور على logo-najd.jpg أو logo-najd.png في جذر المشروع');
}

$iconsDir = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'icons';
if (!is_dir($iconsDir) && !mkdir($iconsDir, 0777, true) && !is_dir($iconsDir)) {
    fail('تعذر إنشاء مجلد الأيقونات: ' . $iconsDir);
}

$sizes = [16, 32, 48, 64, 180, 192, 256, 384, 512];

if (extension_loaded('imagick') && class_exists('Imagick')) {
    // Use Imagick
    try {
        $Imagick = 'Imagick';
        $ImagickPixel = 'ImagickPixel';
        $COMPOSITE_DEFAULT = 0; // Imagick::COMPOSITE_DEFAULT

        $base = new $Imagick($logo);
        $base->setBackgroundColor(new $ImagickPixel('transparent'));
        foreach ($sizes as $size) {
            $thumb = clone $base;
            // Best fit, keep aspect ratio
            $thumb->thumbnailImage($size, $size, true, true);
            $w = $thumb->getImageWidth();
            $h = $thumb->getImageHeight();
            if ($w !== $size || $h !== $size) {
                $canvas = new $Imagick();
                $canvas->newImage($size, $size, new $ImagickPixel('transparent'));
                $canvas->setImageFormat('png');
                $x = (int) floor(($size - $w)/2); $y = (int) floor(($size - $h)/2);
                $canvas->compositeImage($thumb, $COMPOSITE_DEFAULT, $x, $y);
                $thumb = $canvas;
            }
            $thumb->setImageFormat('png');
            $out = $iconsDir . DIRECTORY_SEPARATOR . 'icon-' . $size . '.png';
            $thumb->writeImage($out);
            $thumb->destroy();
            logmsg("تم إنشاء $out");
        }
        // Apple alias
        @copy($iconsDir . DIRECTORY_SEPARATOR . 'icon-180.png', $iconsDir . DIRECTORY_SEPARATOR . 'apple-touch-icon-180.png');

        // favicon.ico with multiple sizes
        $ico = new $Imagick();
        foreach ([16, 32, 48] as $s) {
            $im = new $Imagick($iconsDir . DIRECTORY_SEPARATOR . 'icon-' . $s . '.png');
            $ico->addImage($im);
        }
        $ico->setImageFormat('ico');
        $ico->writeImage($root . DIRECTORY_SEPARATOR . 'favicon.ico');
        logmsg('تم إنشاء favicon.ico');
    } catch (Throwable $e) {
        fail('فشل باستخدام Imagick: ' . $e->getMessage());
    }
} elseif (extension_loaded('gd') && function_exists('imagecreatefromstring')) {
    // Use GD
    $data = @file_get_contents($logo);
    if ($data === false) fail('تعذر قراءة الشعار: ' . $logo);
    $srcImg = @imagecreatefromstring($data);
    if (!$srcImg) fail('تعذر فتح الصورة، تأكد أن الملف صورة صالحة');
    foreach ($sizes as $size) {
        $dest = imagecreatetruecolor($size, $size);
        imagealphablending($dest, false);
        imagesavealpha($dest, true);
        $transparent = imagecolorallocatealpha($dest, 0, 0, 0, 127);
        imagefilledrectangle($dest, 0, 0, $size, $size, $transparent);
        $srcW = imagesx($srcImg); $srcH = imagesy($srcImg);
        $scale = min($size / $srcW, $size / $srcH);
        $newW = (int) floor($srcW * $scale);
        $newH = (int) floor($srcH * $scale);
        $dstX = (int) floor(($size - $newW) / 2);
        $dstY = (int) floor(($size - $newH) / 2);
        imagecopyresampled($dest, $srcImg, $dstX, $dstY, 0, 0, $newW, $newH, $srcW, $srcH);
        $out = $iconsDir . DIRECTORY_SEPARATOR . 'icon-' . $size . '.png';
        if (!imagepng($dest, $out, 9)) fail('تعذر حفظ: ' . $out);
        imagedestroy($dest);
        logmsg("تم إنشاء $out");
    }
    $apple = $iconsDir . DIRECTORY_SEPARATOR . 'apple-touch-icon-180.png';
    @copy($iconsDir . DIRECTORY_SEPARATOR . 'icon-180.png', $apple);
    // ICO from PNG 32x32
    $pngPath = $iconsDir . DIRECTORY_SEPARATOR . 'icon-32.png';
    if (is_file($pngPath)) {
        $pngData = file_get_contents($pngPath);
        $icoPath = $root . DIRECTORY_SEPARATOR . 'favicon.ico';
        $ico  = pack('vvv', 0, 1, 1);
        $width = 32; $height = 32; $colors = 0; $reserved = 0; $planes = 1; $bitcount = 32;
        $bytesInRes = strlen($pngData);
        $imageOffset = 6 + 16;
        $ico .= pack('CCCCvvVV', $width % 256, $height % 256, $colors, $reserved, $planes, $bitcount, $bytesInRes, $imageOffset);
        $ico .= $pngData;
        if (file_put_contents($icoPath, $ico) === false) fail('تعذر إنشاء favicon.ico');
        logmsg('تم إنشاء favicon.ico');
    } else {
        logmsg('لم يتم العثور على icon-32.png لتوليد favicon.ico');
    }
} else {
    fail("لا يوجد امتداد لمعالجة الصور مثبت (GD أو Imagick).\n- لتفعيل GD في XAMPP على ويندوز: افتح php.ini وابحث عن ;extension=gd ثم أزل ; وأعد تشغيل Apache.\n- أو ثبّت Imagick وأعد التشغيل.");
}

logmsg('اكتملت عملية توليد الأيقونات.');
