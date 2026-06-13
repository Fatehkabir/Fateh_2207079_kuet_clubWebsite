<?php
$css_content = file_get_contents('copyMyClub.css');
$css_content = preg_replace('!/\*.*?\*/!s', '', $css_content);
$css_lines = explode("\n", $css_content);
$css_lines = array_filter($css_lines, function($line) {
    return trim($line) !== '';
});
file_put_contents('copyMyClub.css', implode("\n", $css_lines));

$html_content = file_get_contents('copyMyclub.html');
$html_content = preg_replace('/<!--.*?-->/s', '', $html_content);
file_put_contents('copyMyclub.html', $html_content);

echo "Comments removed successfully.\n";
?>
