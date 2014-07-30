<?php
$locales = array(
    "en" => "en_US.UTF-8",
    "de" => "de_DE.UTF-8"
);

if (is_dir("./setups/$ADEI_SETUP/po")) $locale_dir = "./setups/$ADEI_SETUP/po";
else $locale_dir = "./po";

$lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
if (!$lang) $lang = "en";

if (isset($locales[$lang])) {
    if (!is_dir("$locale_dir/$lang")) $lang = "en";
} else $lang = "en";

$locale = $locales[$lang];


if (setlocale(LC_MESSAGES, $locale)) {
    bindtextdomain("adei", $locale_dir);
    textdomain("adei");
}
?>