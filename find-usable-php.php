<?php

$minimumPhpVersion = '7.4';

//check for valet
$use_valet = !empty(shell_exec('command -v valet'));

// First, check if the system's linked "php" is 7.4+; if so, return that. This
// is the most likely, most ideal, and fastest possible case
if ($use_valet) {
    $linkedPhpVersion = shell_exec('valet php -r "echo phpversion();"');
} else {
    $linkedPhpVersion = shell_exec('php -r "echo phpversion();"');
}

if (version_compare($linkedPhpVersion, $minimumPhpVersion) >= 0) {
    if ($use_valet) {
        echo exec('valet php -dxdebug.remote_enable=true -dxdebug.mode=debug -dxdebug.start_with_request=yes -r \'echo $_SERVER["_"];\'');
    } else {
        echo exec('which php');
    }

    return;
}

// If not, let's find it whether we have a version of PHP installed that's 7.4+;
$phps = explode(PHP_EOL, trim(shell_exec('brew list --formula | grep php')));

// Normalize version numbers
$phps = array_reduce($phps, function ($carry, $php) {
    $carry[$php] = presumePhpVersionFromBrewFormulaName($php);

    return $carry;
}, []);

// Filter out older versions of PHP
$modernPhps = array_filter($phps, function ($php) use ($minimumPhpVersion) {
    return version_compare($php, $minimumPhpVersion) >= 0;
});

// If we don't have any modern versions of PHP, throw an error
if (empty($modernPhps)) {
    throw new Exception('Sorry, but you do not have a version of PHP installed that is compatible with Charcoal Conductor (7.4+).');
}

// Sort newest version to oldest
sort($modernPhps);
$modernPhps = array_reverse($modernPhps);

// Grab the highest, set as $foundVersion, and output its path
$foundVersion = reset($modernPhps);
echo getPhpExecutablePath(array_search($foundVersion, $phps));

/**
 * Function definitions.
 */

/**
 * Extract PHP executable path from PHP Version.
 * Copied from Brew.php and modified.
 *
 * @param  string|null  $phpFormulaName  For example, "php@7.4"
 * @return string
 */
function getPhpExecutablePath(string $phpFormulaName = null)
{
    $brewPrefix = exec('printf $(brew --prefix)');

    // Check the default `/opt/homebrew/opt/php@7.4/bin/php` location first
    if (file_exists($brewPrefix."/opt/{$phpFormulaName}/bin/php")) {
        return $brewPrefix."/opt/{$phpFormulaName}/bin/php";
    }

    // Check the `/opt/homebrew/opt/php71/bin/php` location for older installations
    $oldPhpFormulaName = str_replace(['@', '.'], '', $phpFormulaName); // php@7.1 to php71
    if (file_exists($brewPrefix."/opt/{$oldPhpFormulaName}/bin/php")) {
        return $brewPrefix."/opt/{$oldPhpFormulaName}/bin/php";
    }

    throw new Exception('Cannot find an executable path for provided PHP version: '.$phpFormulaName);
}

function presumePhpVersionFromBrewFormulaName(string $formulaName)
{
    if ($formulaName === 'php') {
        // Figure out its link
        $details = json_decode(shell_exec("brew info $formulaName --json"));

        if (! empty($details[0]->aliases[0])) {
            $formulaName = $details[0]->aliases[0];
        } else {
            return null;
        }
    }

    if (strpos($formulaName, 'php@') === false) {
        return null;
    }

    return substr($formulaName, strpos($formulaName, '@') + 1);
}
