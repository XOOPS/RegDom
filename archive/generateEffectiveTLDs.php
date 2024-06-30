#!/usr/bin/env php
<?php
/*
 * Florian Sager, 06.08.2008, sager@agitos.de
 *
 * Auto-Generate PHP array tree that contains all TLDs from the URL (see below);
 * The output has to be copied to reputation-libs/effectiveTLDs.inc.php
 *
 *
 */

// DEFINE('URL', 'https://publicsuffix.org/list/public_suffix_list.dat');
define('URL', 'data/public_suffix_list.dat');

if (PHP_SAPI != 'cli') {
    exit;
}

$format = 'json';
if ($argc > 1) {
    if ('perl' == $argv[1]) {
        $format = 'perl';
    } elseif ('c' == $argv[1]) {
        $format = 'c';
    } elseif ('php' == $argv[1]) {
        $format = 'php';
    }
}

/*
 * Does $search start with $startstring?
 */
function startsWith($search, $startstring)
{
    return (substr($search, 0, strlen($startstring)) == $startstring);
}

/*
 * Does $search end with $endstring?
 */
function endsWith($search, $endstring)
{
    return (substr($search, -strlen($endstring)) == $endstring);
}


function buildSubdomain(&$node, $tldParts)
{
    $dom = trim(array_pop($tldParts));

    $isNotDomain = false;
    if (startsWith($dom, '!')) {
        $dom = substr($dom, 1);
        $isNotDomain = true;
    }

    if (!array_key_exists($dom, $node)) {
        if ($isNotDomain) {
            $node[$dom] = ['!' => ''];
        } else {
            $node[$dom] = [];
        }
    }

    if (!$isNotDomain && count($tldParts)>0) {
        buildSubdomain($node[$dom], $tldParts);
    }
}

function printNode($key, $valueTree, $isAssignment = false, $depth = 0)
{
    global $format;

    if ($isAssignment) {
        if ('perl' == $format) {
            echo "$key = {";
        } else {
            echo "$key = array(";
        }
    } else {
        if (0 == strcmp($key, '!')) {
            if ('perl' == $format) {
                echo "'!' => {}";
            } else {
                echo "'!' => ''";
            }
            return;
        } else {
            if ('perl' == $format) {
                echo "'$key' => {";
            } else {
                echo str_repeat('  ', $depth)."'$key' => array(";
            }
        }
    }

    $keys = array_keys($valueTree);

    for ($i=0; $i<count($keys); $i++) {
        $key = $keys[$i];
        echo "\n";
        printNode($key, $valueTree[$key], false, $depth + 1);

        if ($i+1 != count($valueTree)) {
            echo ',';
        } else {
            echo '';
        }
    }

    if ('perl' == $format) {
        echo '}';
    } else {
        echo ')';
    }
}

// sample: root(3:ac(5:com,edu,gov,net,ad(3:nom,co!,*)),de,com)

function printNode_C($key, $valueTree)
{
    echo "$key";

    $keys = array_keys($valueTree);

    if (count($keys)>0) {
        if (0 == strcmp($keys['!'], '!')) {
            echo '!';
        } else {
            echo '(' .count($keys). ':';

            for ($i=0; $i<count($keys); $i++) {
                $key = $keys[$i];

                // if (count($valueTree[$key])>0) {
                    printNode_C($key, $valueTree[$key]);
                // }

                if ($i+1 != count($valueTree)) {
                    echo ',';
                }
            }

            echo ')';
        }
    }
}

// --- main ---

error_reporting(E_ERROR);

$tldTree = [];
$list = file_get_contents(URL);
// $list = "bg\na.bg\n0.bg\n!c.bg\n";
$lines = explode("\n", $list);
$licence = true;
$commentsection = '';

foreach ($lines as $line) {
    if ($licence && startsWith($line, '//')) {
        if ('perl' == $format) {
            $commentsection .= '# ' .substr($line, 2)."\n";
        } else {
            $commentsection .= $line."\n";
        }

        if (startsWith($line, '// ***** END LICENSE BLOCK')) {
            $licence = false;
            $commentsection .= "\n";
        }
        continue;
    }

    if (startsWith($line, '//') || '' == $line) {
        continue;
    }

    // this must be a TLD
    $tldParts = explode('.', $line);

    buildSubdomain($tldTree, $tldParts);
}

// print_r($tldTree);

/*
$tldTree = array(
    'de' => array(),		// test.agitos.de --> agitos.de
    'uk' => array(
        'co' => array(),	// test.agitos.co.uk --> agitos.co.uk
        'xy' => array('!'),	// test.agitos.xy.uk --> xy.uk
        '*' => array()		// test.agitos.ab.uk --> agitos.ab.uk
    )
);
*/

switch ($format) {
    case 'c':
        echo $commentsection."\n";
        echo "char* tldString = \"";
        printNode_C('root', $tldTree);
        echo "\";\n";
        break;
    case 'perl':
        echo $commentsection."\n";
        print "package effectiveTLDs;\n\n";
        printNode("\$tldTree", $tldTree, true);
        echo ";\n";
        break;
    case 'php':
        echo "<?php\n";
        echo $commentsection."\n";
        printNode("\$tldTree", $tldTree, true);
        echo ";\n";
        echo "return \$tldTree;\n";
        break;
    default:
        echo json_encode($tldTree);
        break;
}
