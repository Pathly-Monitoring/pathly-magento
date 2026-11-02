<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

/*
 * Phrases of the module and their translations.
 *   php dev/i18n.php          list the phrases (one per line)
 *   php dev/i18n.php --check  fail if a phrase is missing from i18n/{fr_FR,de_DE,es_ES}.csv,
 *                             or if a CSV has a phrase the module no longer uses
 * Phrases: __('...') in PHP and templates (concatenated literals included),
 * translatable labels of system.xml, acl.xml and widget.xml.
 */
$root = dirname(__DIR__);
$phrases = [];

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    if (preg_match('#^(Test|dev|vendor|build|publication|\.git)/#', $path)) {
        continue;
    }
    $ext = $file->getExtension();
    if ($ext === 'php' || $ext === 'phtml') {
        foreach (phpPhrases(file_get_contents($file->getPathname())) as $phrase) {
            $phrases[$phrase] = true;
        }
    } elseif ($ext === 'xml') {
        foreach (xmlPhrases(file_get_contents($file->getPathname())) as $phrase) {
            $phrases[$phrase] = true;
        }
    }
}
$phrases = array_keys($phrases);
sort($phrases);

if (!in_array('--check', $argv, true)) {
    echo implode("\n", $phrases), "\n";
    exit(0);
}
$failed = false;
foreach (['fr_FR', 'de_DE', 'es_ES'] as $locale) {
    $csv = $root . '/i18n/' . $locale . '.csv';
    $known = [];
    $handle = fopen($csv, 'r');
    while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        if (count($row) !== 2 || trim($row[1]) === '') {
            fprintf(STDERR, "%s: malformed row %s\n", $locale, json_encode($row));
            $failed = true;
            continue;
        }
        $known[$row[0]] = $row[1];
    }
    fclose($handle);
    foreach (array_diff($phrases, array_keys($known)) as $missing) {
        fprintf(STDERR, "%s: missing \"%s\"\n", $locale, $missing);
        $failed = true;
    }
    foreach (array_diff(array_keys($known), $phrases) as $extra) {
        fprintf(STDERR, "%s: unused \"%s\"\n", $locale, $extra);
        $failed = true;
    }
    foreach ($known as $source => $translation) {
        if (substr_count($source, '%1') !== substr_count($translation, '%1')) {
            fprintf(STDERR, "%s: placeholder %%1 lost in \"%s\"\n", $locale, $source);
            $failed = true;
        }
    }
}
echo $failed ? "Translations incomplete\n" : count($phrases) . " phrases, translated in fr_FR, de_DE and es_ES\n";
exit($failed ? 1 : 0);

/**
 * Phrases of __() calls, with concatenated literals joined.
 *
 * @param string $code
 * @return string[]
 */
function phpPhrases(string $code): array
{
    $tokens = array_values(array_filter(
        token_get_all($code),
        fn ($t) => !is_array($t) || !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
    ));
    $found = [];
    foreach ($tokens as $i => $token) {
        if (!is_array($token) || $token[0] !== T_STRING || $token[1] !== '__' || ($tokens[$i + 1] ?? null) !== '(') {
            continue;
        }
        $text = '';
        for ($j = $i + 2; isset($tokens[$j]); $j++) {
            $part = $tokens[$j];
            if (is_array($part) && $part[0] === T_CONSTANT_ENCAPSED_STRING) {
                $text .= literal($part[1]);
            } elseif ($part !== '.') {
                break;
            }
        }
        if ($text !== '') {
            $found[] = $text;
        }
    }
    return $found;
}

/**
 * Value of a PHP string literal.
 *
 * @param string $literal
 * @return string
 */
function literal(string $literal): string
{
    $body = substr($literal, 1, -1);
    return $literal[0] === "'" ? strtr($body, ['\\\\' => '\\', "\\'" => "'"]) : stripcslashes($body);
}

/**
 * Translatable texts of a configuration file.
 *
 * @param string $xml
 * @return string[]
 */
function xmlPhrases(string $xml): array
{
    $doc = new DOMDocument();
    $doc->loadXML($xml);
    $found = [];
    foreach ((new DOMXPath($doc))->query('//*[@translate]') as $node) {
        $attribute = $node->getAttribute('translate');
        if ($attribute === 'true') {
            $found[] = trim($node->textContent);
            continue;
        }
        foreach (preg_split('/\s+/', $attribute) as $name) {
            if ($node->hasAttribute($name)) {
                $found[] = $node->getAttribute($name);
            }
            foreach ($node->childNodes as $child) {
                if ($child instanceof DOMElement && $child->tagName === $name) {
                    $found[] = trim($child->textContent);
                }
            }
        }
    }
    return $found;
}
