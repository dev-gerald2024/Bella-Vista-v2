<?php
/* Checks the logic of tools/download-fonts.php WITHOUT internet, using a sample of the CSS that Google Fonts returns.
   It proves the generated css/fonts.css points at local files and keeps only the latin subsets. It does NOT prove that the
   download itself works: that needs internet (php tools/download-fonts.php). */
if (PHP_SAPI !== 'cli') exit("CLI only\n");
chdir(dirname(__DIR__));
require 'tools/download-fonts.php';
$sample = <<<CSS
/* cyrillic-ext */
@font-face { font-family: 'Jost'; font-style: normal; font-weight: 400; font-display: swap; src: url(https://fonts.gstatic.com/s/jost/v15/aaa.woff2) format('woff2'); unicode-range: U+0460-052F; }
/* latin-ext */
@font-face { font-family: 'Jost'; font-style: normal; font-weight: 400; font-display: swap; src: url(https://fonts.gstatic.com/s/jost/v15/bbb.woff2) format('woff2'); unicode-range: U+0100-02AF; }
/* latin */
@font-face { font-family: 'Jost'; font-style: normal; font-weight: 400; font-display: swap; src: url(https://fonts.gstatic.com/s/jost/v15/ccc.woff2) format('woff2'); unicode-range: U+0000-00FF; }
/* latin */
@font-face { font-family: 'Cormorant Garamond'; font-style: italic; font-weight: 500; font-display: swap; src: url(https://fonts.gstatic.com/s/cormorantgaramond/v16/ddd.woff2) format('woff2'); unicode-range: U+0000-00FF; }
CSS;
$fetched = [];
$out = build_local_css($sample, function ($url, $name) use (&$fetched) { $fetched[$url] = $name; });
$pass = 0; $fail = 0;
function check($l, $a, $e) { global $pass, $fail; $ok = $a === $e; $ok ? $pass++ : $fail++; printf("  [%s] %s%s\n", $ok ? 'PASS' : 'FAIL', $l, $ok ? '' : ' got=' . json_encode($a) . ' expected=' . json_encode($e)); }
check('only latin and latin-ext faces are kept (3 of 4 blocks)', substr_count($out, '@font-face'), 3);
check('cyrillic block dropped', str_contains($out, 'aaa.woff2'), false);
check('no remote URL is left in the generated CSS', str_contains($out, 'fonts.gstatic.com'), false);
check('every src points to ../assets/fonts/<file>.woff2 (css/ -> assets/fonts/)', preg_match_all('#url\(\.\./assets/fonts/[a-z0-9.-]+\.woff2\)#', $out), 3);
check('file names carry family, style, weight and subset', array_values($fetched), ['jost-normal-400-latin-ext.woff2', 'jost-normal-400-latin.woff2', 'cormorant-garamond-italic-500-latin.woff2']);
check('font-display: swap kept (text shows at once)', substr_count($out, 'font-display: swap'), 3);
check('index.html loads css/fonts.css', str_contains(file_get_contents('index.html'), 'css/fonts.css'), true);
check('CSP allows local fonts (font-src self)', (bool)preg_match("/font-src 'self'/", file_get_contents('.htaccess')), true);
printf("\n%d passed, %d failed\n", $pass, $fail); exit($fail ? 1 : 0);
