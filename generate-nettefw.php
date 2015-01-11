<?php

$version = $argc > 1 ? $argv[1] : NULL;

if ($version === NULL) {
	echo "ERROR: Specify version of Nette as first argument\n";
	exit(1);
}
$escapedVersion = str_replace('.', '\.', $version);

$packagePath = __DIR__ . '/nettefw.docset';
$contentsPath = $packagePath . '/Contents';
$resourcesPath = $contentsPath . '/Resources';
$documentsPath = $resourcesPath . '/Documents';

exec("rm -rf " . $resourcesPath);
exec("mkdir -p " . $resourcesPath);
exec('wget --recursive --domains=api.nette.org --convert-links --accept-regex=\/' . $escapedVersion . '\/ http://api.nette.org/' . $version . '/');
exec("mv " . __DIR__ . "/api.nette.org/" . $version . " " . $documentsPath);
exec("rm -r " . __DIR__ . "/api.nette.org/");
exec("mv " . $documentsPath . '/resources/style.css* ' . $documentsPath . '/resources/style.css');

file_put_contents($contentsPath . "/Info.plist", <<<ENDE
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
	<key>CFBundleIdentifier</key>
	<string>nettefw</string>
	<key>CFBundleName</key>
	<string>Nette Framework</string>
	<key>DocSetPlatformFamily</key>
	<string>nettefw</string>
	<key>isDashDocset</key>
	<true/>
	<key>dashIndexFilePath</key>
	<string>index.html</string>
        <key>isJavaScriptEnabled</key>
        <true/>
</dict>
</plist>
ENDE
);
copy(__DIR__ . "/icon.png", $packagePath . "/icon.png");
copy(__DIR__ . "/icon@2x.png", $packagePath . "/icon@2x.png");

$db = new sqlite3($resourcesPath . "/docSet.dsidx");
$db->query("CREATE TABLE searchIndex(id INTEGER PRIMARY KEY, name TEXT, type TEXT, path TEXT)");
$db->query("CREATE UNIQUE INDEX anchor ON searchIndex (name, type, path)");

$dir = new DirectoryIterator($documentsPath);
foreach ($dir as $fileinfo) {
	if (!$fileinfo->isDot() && $fileinfo->isFile() && $fileinfo->getExtension() === 'html') {
		$file = $fileinfo->getFilename();

		$content = file_get_contents($documentsPath . '/' . $file);
		$content = str_replace('<ul><li><a href="http://api.nette.org/releases"><span>Other releases</span></a></li><li><a href="http://nette.org"><span>Nette homepage</span></a></li></ul>', '', $content);
		$content = str_replace('http://api.nette.org/' . $version . '/', '', $content);
		$content = str_replace('autofocus', '', $content);
		file_put_contents($documentsPath . '/' . $file, $content);
	}
}

// TODO
// - Annotation
// - Constructor
// - Event
// - Hook
// - Attribute
// - Define
// - Property
// - Extension
// - Shortcut
// - Component
// - Global
// - Parameter
// - Variable
// - Callback
// - Constant
// - Error
// - File
// - Object
// - Trait

$dom = new DomDocument();

foreach (array("tree") as $file) {
	@$dom->loadHTMLFile($documentsPath . "/" . $file . ".html");
	// Namespace
	if ($el = $dom->getElementById("groups")) {
		foreach ($el->getElementsByTagName("a") as $link) {
			$href = $link->getAttribute('href');
			$name = (string) $link->textContent;

			// echo $name, " -> ", $href, "\n";
			$db->query("INSERT OR IGNORE INTO searchIndex(name, type, path) VALUES (\"$name\",\"Namespace\",\"$href\")");
		}
	}
	// Class, Interface, Exception, Function
	if ($el = $dom->getElementById("elements")) {
		$types = array('Class', 'Interface', 'Exception', 'Function');
		foreach ($el->getElementsByTagName("ul") as $i => $list) {
			foreach ($list->getElementsByTagName('a') as $link) {
				$href = $link->getAttribute('href');
				$name = (string) $link->textContent;
				$pos = strrpos($name, '\\');
				$name = substr($name, $pos !== FALSE ? $pos + 1 : 0);

				// echo $name, " -> ", $href, "\n";
				$db->query("INSERT OR IGNORE INTO searchIndex(name, type, path) VALUES (\"$name\",\"" . $types[$i] . "\",\"$href\")");
			}
		}
	}
	// Class
	if ($el = $dom->getElementById("content")) {
		foreach ($el->getElementsByTagName("a") as $link) {
			$href = $link->getAttribute('href');
			$name = (string) $link->textContent;
			$pos = strrpos($name, '\\');
			$name = substr($name, $pos !== FALSE ? $pos + 1 : 0);

			// echo $name, " -> ", $href, "\n";
			$db->query("INSERT OR IGNORE INTO searchIndex(name, type, path) VALUES (\"$name\",\"Class\",\"$href\")");
		}
	}
}

foreach ($dir as $fileinfo) {
	if (!$fileinfo->isDot() && $fileinfo->isFile() && $fileinfo->getExtension() === 'html') {
		$file = $fileinfo->getFilename();

		@$dom->loadHTMLFile($documentsPath . "/" . $file);

		if ($el = $dom->getElementById("methods")) {
			foreach ($el->getElementsByTagName("tr") as $row) {
				$href = $file . '#' . $row->getAttribute('id');
				if (!$code = $row->getElementsByTagName('code')->item(1)) {
					$code = $row->getElementsByTagName('code')->item(0);
				}
				$link = $code->getElementsByTagName('a')->item(0);
				$name = (string) $link->textContent;

				// echo $name, " -> ", $href, "\n";
				$db->query("INSERT OR IGNORE INTO searchIndex(name, type, path) VALUES (\"$name\",\"Method\",\"$href\")");
			}
		}
	}
}

exec('tar --exclude=".DS_Store" -cvzf nettefw.tgz nettefw.docset');
