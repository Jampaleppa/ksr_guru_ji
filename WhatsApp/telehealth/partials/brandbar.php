<?php
/* =====================================================================
   telehealth/partials/brandbar.php
   ---------------------------------------------------------------------
   Branded top bar for EVERY Doctor Assist page.

   Shows: company logo, brand name, tagline, and quick links.
   Pages keep their own existing navbar below this bar, so navigation
   is unchanged — this only adds identity/branding on top.

   Included by: every .php page in WhatsApp/public/telehealth/
   ===================================================================== */

/* Resolve the logo once, with graceful fallbacks. The module lives at
   <root>/WhatsApp/public/telehealth/, so the shop root is 4 levels up. */

/* helper.php defines h(); several pages use their own escaper. Make sure a
   safe one exists so this partial can never fatal. */
if (!function_exists('h')) {
    function h(mixed $v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (!function_exists('telehealth_logo_candidates')) {
    /** Absolute paths on disk to try, best first. */
    function telehealth_logo_candidates(): array
    {
        // __DIR__ = <root>/WhatsApp/public/telehealth/partials
        // 4 levels up = project root.
        $root = dirname(__DIR__, 4);
        return [
            $root . '/company_logo.jpg',
            $root . '/ksr guru ji.jpg',
            $root . '/KSR_GURU_JI.png',
            __DIR__ . '/../assets/company_logo.jpg',
        ];
    }
}

if (!function_exists('telehealth_logo_url')) {
    /**
     * Web path of the first logo that exists on disk.
     *
     * $ksrDepth = how many folder levels the page sits below the project
     * root. telehealth/index.php  -> 0, needs no "../"
     *   telehealth/medicine/x.php -> 1, needs one "../"
     *
     * NOTE: the logo lives at <root>/company_logo.jpg, and the module root
     * is <root>/WhatsApp/public/telehealth. So the correct climb is
     * $ksrDepth + 3 (telehealth -> public -> WhatsApp -> root).
     */
    function telehealth_logo_url(int $ksrDepth = 0): ?string
    {
        static $name = null;   // basename of the winning file
        static $found = false; // whether the scan has run

        if (!$found) {
            $found = true;
            foreach (telehealth_logo_candidates() as $candidate) {
                if (is_file($candidate)) {
                    $name = basename($candidate);
                    break;
                }
            }
        }

        if (!$name) {
            return null;
        }

        $up = $ksrDepth + 3;   // climb out of the module to the project root
        return str_repeat('../', $up) . rawurlencode($name);
    }
}

if (!function_exists('telehealth_brandbar')) {

    /**
     * Render the branded top bar.
     *
     * @param string $active  page key to highlight in the quick links
     * @param int    $ksrDepth   folder levels this page sits below the project root
     */
    function telehealth_brandbar(string $active = '', int $ksrDepth = 0): void
    {
        $logo    = telehealth_logo_url($ksrDepth);
        $tagline = 'Everything Your Family Needs, Every Day';

        /* Each entry: [href, label, extra page keys that mean "I'm here"].
           The aliases matter because a page can be reached by several names —
           e.g. doctor-dashboard.php and doctor-shivukumar-dashboard.php are
           both the "Dashboard", so both must light up the same link. */
        /* Home link must climb out of sub-folders too, else a page in
           medicine/ resolves to medicine/index.php and 404s. */
        $up    = str_repeat('../', $ksrDepth);
        $links = [
            'home'          => [$up . 'index.php', 'Home', ['index', 'landing']],
            'consult'       => [$up . 'consultation-workspace.php', 'Consult', ['consultation-workspace', 'consultation-room', 'doctor-consultation-room', 'consultation']],
            'appointments'  => [$up . 'doctor-appointments.php', 'Appointments', ['doctor-appointments', 'my-appointments', 'appointments']],
            'prescriptions' => [$up . 'doctor-prescriptions.php', 'Prescriptions', ['doctor-prescriptions', 'build-prescription', 'prescriptions']],
            'medicines'     => [$up . 'medicine/tablet.php', 'Medicines', ['medicine-tablet', 'medicines', 'pharmacy-dashboard']],
        ];

        echo '<div class="ksr-brandbar">';
        echo '  <div class="ksr-brandbar__inner">';

        /* ---- logo + wordmark ---- */
        echo '    <a class="ksr-brandbar__brand" href="' . h($up) . 'index.php">';

        echo '      <span class="ksr-brandbar__logo">';
        if ($logo !== null) {
            $alt = defined('APP_NAME') ? APP_NAME : 'KSR GURU JI';
            echo '<img src="' . h($logo) . '" alt="' . h($alt) . ' logo"'
                . ' onerror="this.parentNode.classList.add(\'is-fallback\');this.remove();">';
        }
        echo '<span class="ksr-brandbar__logo-fallback" aria-hidden="true">KSR</span>';
        echo '</span>';

        echo '      <span class="ksr-brandbar__text">';
        echo '        <strong>KSR GURU JI</strong>';
        echo '        <small>' . h($tagline) . '</small>';
        echo '        <em>Doctor Assist &middot; Telehealth</em>';
        echo '      </span>';
        echo '    </a>';

        /* ---- quick links ---- */
        echo '    <nav class="ksr-brandbar__links" aria-label="Doctor Assist sections">';
        foreach ($links as $key => $item) {
            [$href, $label, $aliases] = $item;
            /* Match when the page declares this key, or any alias it also
               answers to. Empty $active = no highlight (e.g. login pages). */
            $on = ($active !== '' && in_array($active, array_merge([$key], $aliases), true));
            $cls = $on ? ' class="is-active" aria-current="page"' : '';
            echo '<a' . $cls . ' href="' . h($href) . '">' . h($label) . '</a>';
        }
        echo '    </nav>';

        /* ---- phone ---- */
        if (defined('TELEHEALTH_AUDIO_CALL_NUMBER')) {
            echo '    <a class="ksr-brandbar__call" href="tel:' . h(TELEHEALTH_AUDIO_CALL_NUMBER) . '">';
            echo '      <i class="fas fa-phone-alt"></i><span>' . h(TELEHEALTH_AUDIO_CALL_NUMBER) . '</span>';
            echo '    </a>';
        }

        echo '  </div>';
        echo '</div>';
    }
}

telehealth_brandbar($ksr_active ?? '', $ksr_depth ?? 0);
