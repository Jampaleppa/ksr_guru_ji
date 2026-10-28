<?php
/* =====================================================================
   telehealth/partials/favicons.php
   ---------------------------------------------------------------------
   Shared favicon / web-app icon links for every Doctor Assist page.
   Included once in <head> — either automatically via brandbar.php
   or manually by standalone pages (doctor-login, doctor-register).

   $ksr_depth (int) = how many folders below WhatsApp/telehealth/
   the calling page sits. Default 0.
   ===================================================================== */
$_fav_depth = ($ksr_depth ?? 0) + 2;  // climb out of telehealth + WhatsApp to root
$_fav_prefix = str_repeat('../', $_fav_depth);
?>
    <link rel="shortcut icon" href="<?= htmlspecialchars($_fav_prefix) ?>favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= htmlspecialchars($_fav_prefix) ?>favicon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= htmlspecialchars($_fav_prefix) ?>icon-192.png">
    <link rel="apple-touch-icon" sizes="192x192" href="<?= htmlspecialchars($_fav_prefix) ?>icon-192.png">
<?php unset($_fav_depth, $_fav_prefix); ?>
