<?php
namespace @@NS@@;

final class Diagnostics
{
    public static function report(\Throwable $error): string
    {
        $id = bin2hex(random_bytes(8));
        // PHP error_log must point outside DOCUMENT_ROOT, with OS ACL and log rotation.
        // No exception message, request content, arguments or stack trace can leak secrets.
        error_log('[@@ID@@][' . $id . '] ' . get_class($error) . ' at ' . $error->getFile() . ':' . $error->getLine());
        return $id;
    }
}
