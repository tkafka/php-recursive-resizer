<?php
class IgnorantRecursiveDirectoryIterator extends RecursiveDirectoryIterator {
    function getChildren() {
        try {
            return new IgnorantRecursiveDirectoryIterator($this->getPathname());
        } catch(UnexpectedValueException $e) {
            return new RecursiveArrayIterator(array());
        }
    }
}

function mkDirIfNotExists($path) {
	if (file_exists($path))
		return true;
	else
		return mkdir($path, 0777, true);
}

if (count($argv)<3) {
	echo "Usage: php resize-folder.php <inputFolder> <ouputFolder> [size]";
}
$inputDirectory = $argv[1];
$outputDirectory = $argv[2];
$size = count($argv)>=4 ? $argv[3] : '1280x1280>';

if ($inputDirectory == $outputDirectory) {
	echo "You are copying folder to iteslf -> loss of data!";
	ecit(1);
}


$inputDirectoryReal = realpath($inputDirectory);
if (!mkDirIfNotExists($outputDirectory)) {
	echo "Couldn't create folder $outputDirectory, exiting.";
	exit(1);
}
$outputDirectoryReal = realpath($outputDirectory);

if ($inputDirectoryReal == $outputDirectoryReal) {
	echo "You are copying folder to iteslf -> loss of data!";
	ecit(1);
}


$mtimes = array();
$mtimesFilePath = $inputDirectoryReal . '/.mtimes';
if (file_exists($mtimesFilePath)) {
	$tmp = file($mtimesFilePath);
	foreach($tmp as $line) {
		$parts = explode(':', $line);
		if (count($parts) == 2) {
			$mtimes[$parts[0]] = $parts[1];
		}
	}
}

echo "input: $inputDirectoryReal\n";
echo "output: $outputDirectoryReal\n";
echo "size: $size\n";
echo "\n";

$Directory = new IgnorantRecursiveDirectoryIterator($inputDirectoryReal);
$Iterator = new RecursiveIteratorIterator($Directory);
$Regex = new RegexIterator($Iterator, '/^.+\.(jpg|png)$/i', RecursiveRegexIterator::GET_MATCH);

foreach($Regex as $pathFilename1 => $value) {
	$path1 = dirname($pathFilename1);
	$path2 = str_replace($inputDirectoryReal, $outputDirectoryReal, $path1);
	$pathFilename2 = str_replace($inputDirectoryReal, $outputDirectoryReal, $pathFilename1);
	$pathPlain = str_replace($inputDirectoryReal, '', $pathFilename1);
	
	$mtime = filemtime($pathFilename1);
	if (isset($mtimes[$pathPlain]) && $mtime == $mtimes[$pathPlain]) {
		echo "x";
		continue;
	} else {
		$mtimes[$pathPlain] = $mtime;
	}
	
	if (mkDirIfNotExists($path2)) {
		$commandLineArray = array(
            'convert',
            '"' . $pathFilename1 . '"',
            '-resize ' . escapeshellarg($size),
			'-auto-orient',
            // '-unsharp 0x6+0.5+0',
            '"' . $pathFilename2 . '"'
        );
        $commandLine = implode(' ', $commandLineArray);

        echo '.';

        $output = array();
        exec($commandLine, $output);

        if (count($output)) {
            echo "\n" . implode("\n", $output) . "\n";
        }
	} else {
		echo "Couldn't create folder $path2.";
	}
}

// save
$str = '';
foreach($mtimes as $file => $mtime) {
	$str .= "$file:$mtime\n";
}
file_put_contents($mtimesFilePath, $str);

