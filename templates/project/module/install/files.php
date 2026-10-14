<?php
// Return closures: SiteUpdate must not depend on new module classes or autoload state.
return static function (string $source, string $root, array $previous, bool $remove = false): array {
    $prefix = '/local/components/@@VENDOR@@/@@MODULE@@.demo';
    $source = $source . '/components/@@VENDOR@@/@@MODULE@@.demo';
    $root = realpath($root);
    if ($root === false || !is_dir($root)) { throw new RuntimeException('Invalid document root'); }
    $assertPath = static function (string $path) use ($root): void {
        if (!str_starts_with($path, $root . '/local/components/@@VENDOR@@/@@MODULE@@.demo/')) {
            throw new RuntimeException('Unowned destination');
        }
        $parent = $path;
        while ($parent !== $root) {
            if (is_link($parent)) { throw new RuntimeException('Symlink rejected'); }
            if (file_exists($parent) && $parent !== $path && !is_dir($parent)) { throw new RuntimeException('Parent is not directory'); }
            $parent = dirname($parent);
        }
        if (file_exists($path) && !is_file($path)) { throw new RuntimeException('Destination is not file'); }
        $parent = dirname($path);
        while (!is_dir($parent)) { $parent = dirname($parent); }
        if (!is_writable($parent) || (file_exists($path) && !is_writable($path))) {
            throw new RuntimeException('Destination not writable');
        }
    };
    $contents = [];
    if (!$remove) {
        if (!is_dir($source)) { throw new RuntimeException('Component source absent'); }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isLink() || !$file->isFile()) { throw new RuntimeException('Invalid source'); }
            $relative = substr($file->getPathname(), strlen($source) + 1);
            $data = file_get_contents($file->getPathname());
            if ($data === false) { throw new RuntimeException('Source unreadable'); }
            $contents[$relative] = $data;
        }
    }
    foreach ($previous as $relative => $hash) {
        if (!is_string($relative) || preg_match('~(^/|\\\\|(^|/)\.\.(/|$)|\x00)~', $relative) || !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/', $hash)) {
            throw new RuntimeException('Invalid owned manifest');
        }
    }
    $paths = $remove ? array_keys($previous) : array_keys($contents);
    if (!$remove && array_diff(array_keys($previous), $paths)) {
        throw new RuntimeException('File deletion requires an explicit reviewed migration');
    }
    $before = [];
    $createdDirs = [];
    foreach ($paths as $relative) {
        $path = $root . $prefix . '/' . $relative;
        $assertPath($path);
        $data = is_file($path) ? file_get_contents($path) : null;
        if ($data === false) { throw new RuntimeException('Destination unreadable'); }
        if ($data !== null && hash('sha256', $data) !== ($previous[$relative] ?? '') && (!$remove && $data !== $contents[$relative] || $remove)) {
            throw new RuntimeException('Foreign or customized file; preserve it and reconcile manually');
        }
        $before[$path] = [$data, $data === null ? 0644 : (fileperms($path) & 0777)];
    }
    $write = static function (string $path, string $data, int $mode): void {
        $tmp = tempnam(dirname($path), '.kit-');
        if ($tmp === false) { throw new RuntimeException('Temporary file failure'); }
        try {
            if (file_put_contents($tmp, $data) !== strlen($data) || !chmod($tmp, $mode) || !rename($tmp, $path)) {
                throw new RuntimeException('Copy failed');
            }
        } finally { if (is_file($tmp)) { unlink($tmp); } }
    };
    $undo = static function () use (&$before, &$createdDirs, $write): void {
        foreach (array_reverse($before, true) as $path => [$data, $mode]) {
            if ($data === null) {
                if (is_file($path) && !unlink($path)) { throw new RuntimeException('Rollback removal failed'); }
            } else { $write($path, $data, $mode); }
        }
        foreach (array_reverse($createdDirs) as $dir) {
            if (is_dir($dir) && count(scandir($dir)) === 2 && !rmdir($dir)) { throw new RuntimeException('Rollback directory failure'); }
        }
    };
    try {
        foreach ($paths as $relative) {
            $path = $root . $prefix . '/' . $relative;
            if ($remove) {
                if (is_file($path) && !unlink($path)) { throw new RuntimeException('Removal failed'); }
            } elseif ($before[$path][0] !== $contents[$relative]) {
                $missing = []; $dir = dirname($path);
                while (!is_dir($dir)) { $missing[] = $dir; $dir = dirname($dir); }
                foreach (array_reverse($missing) as $dir) {
                    if (!mkdir($dir, 0755)) { throw new RuntimeException('Directory creation failed'); }
                    $createdDirs[] = $dir;
                }
                $write($path, $contents[$relative], $before[$path][1]);
                if (hash_file('sha256', $path) !== hash('sha256', $contents[$relative])) { throw new RuntimeException('Copy verification failed'); }
            }
        }
    } catch (Throwable $error) { $undo(); throw $error; }
    return ['manifest' => array_map(static fn(string $data): string => hash('sha256', $data), $contents), 'undo' => $undo];
};
