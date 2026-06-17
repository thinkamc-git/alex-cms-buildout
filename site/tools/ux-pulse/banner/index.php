<?php
// UX Pulse — Banner Generator
// Streams a PNG directly — no file saving, no caching
// Notion fetches this URL and gets the image back instantly
// GET /tools/ux-pulse/banner/?token=uxp_7k2mN9qR4vWxL8hT&issue=003&date=June+16,+2026&headline1=Vibe+Slop+Is+Real&headline2=&gradient=amber-coral

require_once __DIR__ . '/../config.php';
uxp_auth();

header('Content-Type: image/png');
header('Cache-Control: public, max-age=604800');

$issue    = isset($_GET['issue'])     ? preg_replace('/[^0-9]/', '', $_GET['issue']) : '001';
$date     = isset($_GET['date'])      ? str_replace('%2C', ',', strip_tags(urldecode($_GET['date'])))      : date('F j, Y');
$line1    = isset($_GET['headline1']) ? str_replace('%2C', ',', strip_tags(urldecode($_GET['headline1']))) : '';
$line2    = isset($_GET['headline2']) ? str_replace('%2C', ',', strip_tags(urldecode($_GET['headline2']))) : '';
$gradient = isset($_GET['gradient'])  ? preg_replace('/[^a-z\-]/', '', $_GET['gradient']) : 'teal-blue';

// Gradient palettes
$gradients = [
    'teal-blue'     => [['r'=>15,  'g'=>110, 'b'=>86],  ['r'=>24,  'g'=>95,  'b'=>165]],
    'purple-pink'   => [['r'=>83,  'g'=>58,  'b'=>183], ['r'=>153, 'g'=>53,  'b'=>86]],
    'amber-coral'   => [['r'=>186, 'g'=>117, 'b'=>23],  ['r'=>153, 'g'=>60,  'b'=>29]],
    'forest-purple' => [['r'=>8,   'g'=>80,  'b'=>65],  ['r'=>83,  'g'=>58,  'b'=>183]],
];
$palette = isset($gradients[$gradient]) ? $gradients[$gradient] : $gradients['teal-blue'];
$c1 = $palette[0];
$c2 = $palette[1];

// Accent colours
$accents = [
    'teal-blue'     => ['r'=>159, 'g'=>225, 'b'=>203],
    'purple-pink'   => ['r'=>244, 'g'=>192, 'b'=>209],
    'amber-coral'   => ['r'=>250, 'g'=>199, 'b'=>117],
    'forest-purple' => ['r'=>159, 'g'=>225, 'b'=>203],
];
$ac = isset($accents[$gradient]) ? $accents[$gradient] : $accents['teal-blue'];

// Canvas 1200x675 (16:9)
$w  = 1200;
$h  = 675;
$im = imagecreatetruecolor($w, $h);
imagesavealpha($im, true);

// Diagonal gradient background
for ($y = 0; $y < $h; $y++) {
    for ($x = 0; $x < $w; $x++) {
        $t = ($x / $w + $y / $h) / 2;
        $r = (int)($c1['r'] + ($c2['r'] - $c1['r']) * $t);
        $g = (int)($c1['g'] + ($c2['g'] - $c1['g']) * $t);
        $b = (int)($c1['b'] + ($c2['b'] - $c1['b']) * $t);
        imagesetpixel($im, $x, $y, imagecolorallocate($im, $r, $g, $b));
    }
}

// Colours
$white       = imagecolorallocate($im, 255, 255, 255);
$white_faint = imagecolorallocatealpha($im, 255, 255, 255, 115);
$white_rule  = imagecolorallocatealpha($im, 255, 255, 255, 100);
$accent_col  = imagecolorallocate($im, $ac['r'], $ac['g'], $ac['b']);
$shape_col   = imagecolorallocatealpha($im, 255, 255, 255, 77);

// Fonts
$font_path = __DIR__ . '/fonts/';
$font_bold = $font_path . 'Barlow-SemiBold.ttf';
$font_reg  = $font_path . 'Barlow-Regular.ttf';
$use_ttf   = file_exists($font_bold) && file_exists($font_reg);

// Watermark issue number
if ($use_ttf) {
    $wm_size = 320;
    $wm_box  = imagettfbbox($wm_size, 0, $font_bold, $issue);
    $wm_w    = abs($wm_box[4] - $wm_box[0]);
    imagettftext($im, $wm_size, 0, $w - $wm_w - 40, $h - 20, $white_faint, $font_bold, $issue);
} else {
    imagestring($im, 5, $w - 160, $h - 80, $issue, $white_faint);
}

// Bauhaus shapes
$sy  = 86;
$r   = 18;
$gap = 16;

$tri_cx = 1020;
imagefilledpolygon($im, [
    $tri_cx,      $sy - $r,
    $tri_cx + $r, $sy + $r,
    $tri_cx - $r, $sy + $r,
], $shape_col);

$cir_cx = $tri_cx + $r + $gap + $r;
imagefilledellipse($im, $cir_cx, $sy, $r * 2, $r * 2, $shape_col);

$sq_x = $cir_cx + $r + $gap;
imagefilledrectangle($im, $sq_x, $sy - $r, $sq_x + $r * 2, $sy + $r, $shape_col);

// Text
$lm = 106;

if ($use_ttf) {
    imagettftext($im, 28,  0, $lm, 100, $accent_col, $font_bold, 'UX PULSE');
    imagettftext($im, 145, 0, $lm, 310, $white,      $font_bold, 'Issue ' . $issue);
    imagettftext($im, 62,  0, $lm, 410, $accent_col, $font_reg,  $date);
    imageline($im, $lm, 460, $w - $lm, 460, $white_rule);
    if ($line1) imagettftext($im, 56, 0, $lm, 570, $white, $font_bold, $line1);
    if ($line2) imagettftext($im, 56, 0, $lm, 638, $white, $font_bold, $line2);
} else {
    imagestring($im, 3, $lm, 80,  'UX PULSE', $accent_col);
    imagestring($im, 5, $lm, 200, 'Issue ' . $issue, $white);
    imagestring($im, 4, $lm, 320, $date, $accent_col);
    imageline($im, $lm, 370, $w - $lm, 370, $white_rule);
    if ($line1) imagestring($im, 4, $lm, 450, $line1, $white);
    if ($line2) imagestring($im, 4, $lm, 520, $line2, $white);
}

// Stream directly — no file saving
imagepng($im);
imagedestroy($im);
